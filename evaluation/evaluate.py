"""سكربت تقييم نماذج الكشف على بيانات اختبار حقيقية.

يشغّل نفس المحللات التي تستخدمها خدمة الذكاء الاصطناعي (ai-service/app/analyzers) على عينة manifest.json،
ثم يحسب المقاييس الفعلية: الدقة، الإحكام، الاستدعاء، F1، مصفوفة الارتباك، معدل الإيجابيات الخاطئة والسلبيات الخاطئة، و AUC.

الاستخدام (من مجلد evaluation، ببيئة ai-service الافتراضية):
    ../ai-service/.venv/Scripts/python.exe evaluate.py --dataset faces_real_vs_fake

منهجية:
  * لا تدريب هنا: النماذج مدرّبة مسبقًا ومصدرها مفتوح.
  * التحقق (validation، 30%) لاختيار عتبة القرار فقط، والاختبار (test، 70%) للأرقام النهائية.
  * نبلّغ عن العتبة الافتراضية 50%، وعن العتبة المضبوطة على التحقق، وعن عتبة المنصة الافتراضية 70%.
  * الملفات التي فشل تحليلها تُحصى وتُعلن، ولا تُحذف بصمت.
  * لا يوجد أي رقم مكتوب يدويًا: كل نسبة في المخرجات محسوبة من التنبؤات المسجلة في scores.jsonl.
"""

from __future__ import annotations

import argparse
import csv
import json
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT.parent / "ai-service"))
sys.path.insert(0, str(ROOT))

from eval_datasets import DATASETS  # noqa: E402

RESULTS_DIR = ROOT / "results"
DATA_DIR = ROOT / "data"


def load_analyzer(media_type: str):
    if media_type == "image":
        from app.analyzers.image import analyze_image
        return analyze_image
    if media_type == "audio":
        from app.analyzers.audio import analyze_audio
        return analyze_audio
    from app.analyzers.video import analyze_video
    return analyze_video


def run_scores(key: str, limit: int | None) -> tuple[list[dict], dict]:
    """يحلل كل عينة ويسجل النتيجة في scores.jsonl (يستأنف من حيث توقف)."""
    manifest = json.loads((DATA_DIR / key / "manifest.json").read_text(encoding="utf-8"))
    analyzer = load_analyzer(manifest["media_type"])
    scores_path = DATA_DIR / key / "scores.jsonl"

    done: dict[str, dict] = {}
    if scores_path.exists():
        for line in scores_path.read_text(encoding="utf-8").splitlines():
            if line.strip():
                row = json.loads(line)
                done[row["path"]] = row

    items = manifest["items"][:limit] if limit else manifest["items"]
    model_info: dict = {}
    with scores_path.open("a", encoding="utf-8") as out:
        for i, item in enumerate(items, 1):
            if item["path"] in done:
                continue
            started = time.perf_counter()
            row = {"path": item["path"], "label": item["label"], "split": item["split"], "source": item["source"]}
            try:
                result = analyzer(item["path"])
                row.update({"probability": result["manipulation_probability"], "model": result["model"], "seconds": round(time.perf_counter() - started, 2)})
            except Exception as exc:  # noqa: BLE001 - نسجل الفشل ولا نخفيه
                row.update({"error": f"{type(exc).__name__}: {exc}"[:200], "seconds": round(time.perf_counter() - started, 2)})
            out.write(json.dumps(row, ensure_ascii=False) + "\n")
            out.flush()
            done[item["path"]] = row
            if i % 10 == 0 or i == len(items):
                print(f"  [{key}] analyzed {i}/{len(items)}", flush=True)

    rows = [done[item["path"]] for item in items if item["path"] in done]
    for row in rows:
        if "model" in row:
            model_info = row["model"]
            break
    return rows, {**manifest, "model_info": model_info}


def confusion(y_true: np.ndarray, y_pred: np.ndarray) -> dict[str, int]:
    return {
        "tp": int(((y_true == 1) & (y_pred == 1)).sum()),
        "fp": int(((y_true == 0) & (y_pred == 1)).sum()),
        "tn": int(((y_true == 0) & (y_pred == 0)).sum()),
        "fn": int(((y_true == 1) & (y_pred == 0)).sum()),
    }


def metrics_from(cm: dict[str, int]) -> dict[str, float]:
    tp, fp, tn, fn = cm["tp"], cm["fp"], cm["tn"], cm["fn"]
    n = tp + fp + tn + fn
    precision = tp / (tp + fp) if (tp + fp) else 0.0
    recall = tp / (tp + fn) if (tp + fn) else 0.0
    return {
        "accuracy": (tp + tn) / n if n else 0.0,
        "precision": precision,
        "recall": recall,
        "f1": 2 * precision * recall / (precision + recall) if (precision + recall) else 0.0,
        "false_positive_rate": fp / (fp + tn) if (fp + tn) else 0.0,
        "false_negative_rate": fn / (fn + tp) if (fn + tp) else 0.0,
    }


def at_threshold(probs: np.ndarray, labels: np.ndarray, threshold: float) -> dict:
    cm = confusion(labels, (probs >= threshold).astype(int))
    return {"threshold": threshold, "confusion_matrix": cm, "n_samples": int(len(labels)), **metrics_from(cm)}


def best_threshold(probs: np.ndarray, labels: np.ndarray) -> float:
    """العتبة التي تعظّم الدقة المتوازنة على بيانات التحقق (إن تساوت نختار الأقرب إلى 50)."""
    best, best_score = 50.0, -1.0
    for t in range(5, 96, 1):
        m = metrics_from(confusion(labels, (probs >= t).astype(int)))
        score = (m["recall"] + (1 - m["false_positive_rate"])) / 2
        if score > best_score + 1e-9 or (abs(score - best_score) <= 1e-9 and abs(t - 50) < abs(best - 50)):
            best, best_score = float(t), score
    return best


def auc(probs: np.ndarray, labels: np.ndarray) -> float | None:
    from sklearn.metrics import roc_auc_score

    if len(set(labels.tolist())) < 2:
        return None
    return float(roc_auc_score(labels, probs))


def three_class(probs: np.ndarray, labels: np.ndarray, fake_t: float = 70.0, real_t: float = 30.0) -> dict:
    """سلوك المنصة الفعلي بثلاثة تصنيفات: حقيقي/غير حاسم/مزيف. الدقة تُحسب على الحالات الحاسمة فقط."""
    fake_pred = probs >= fake_t
    real_pred = probs < real_t
    decided = fake_pred | real_pred
    correct = (fake_pred & (labels == 1)) | (real_pred & (labels == 0))
    return {
        "fake_threshold": fake_t,
        "real_threshold": real_t,
        "uncertain_rate": float((~decided).mean()) if len(probs) else 0.0,
        "accuracy_on_decided": float(correct[decided].mean()) if decided.any() else None,
        "decided_samples": int(decided.sum()),
    }


def evaluate(key: str, limit: int | None) -> dict:
    rows, manifest = run_scores(key, limit)
    ok = [r for r in rows if "probability" in r]
    failed = [r for r in rows if "probability" not in r]

    def arrays(split: str):
        subset = [r for r in ok if r["split"] == split]
        return np.array([r["probability"] for r in subset]), np.array([r["label"] for r in subset])

    val_p, val_y = arrays("validation")
    test_p, test_y = arrays("test")
    if len(test_y) == 0 or len(set(test_y.tolist())) < 2:
        raise SystemExit(f"Not enough test samples for {key}: have {len(test_y)}")

    tuned = best_threshold(val_p, val_y) if len(val_y) and len(set(val_y.tolist())) == 2 else 50.0
    default = at_threshold(test_p, test_y, 50.0)

    report = {
        "dataset": f"{manifest['repo']} ({key})",
        # معرّف قصير ثابت لمجموعة البيانات؛ يُستخدم لتخزين مقاييس عدة مجموعات تحت نسخة النموذج نفسها
        # دون أن يطغى تقييم على آخر (نموذج الصور الواحد يُقاس على وجوه مزيفة وصور مولَّدة منفصلتين مثلًا)
        "dataset_key": key,
        "media_type": manifest["media_type"],
        "split": "test",
        "evaluated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "model": {k: manifest["model_info"].get(k) for k in ("name", "version", "source", "license")},
        "n_total_attempted": len(rows),
        "n_failed": len(failed),
        "failed_examples": [{"source": r["source"], "error": r["error"]} for r in failed[:5]],
        "n_validation": int(len(val_y)),
        "n_test": int(len(test_y)),
        "class_balance_test": {"real": int((test_y == 0).sum()), "fake": int((test_y == 1).sum())},
        "auc": auc(test_p, test_y),
        "seconds_per_sample_mean": round(float(np.mean([r["seconds"] for r in ok])), 2) if ok else None,
        "operating_points": {
            "default_50": default,
            "validation_tuned": at_threshold(test_p, test_y, tuned),
            "platform_fake_threshold_70": at_threshold(test_p, test_y, 70.0),
        },
        "platform_three_class": three_class(test_p, test_y),
        "data_note": manifest["note"],
        "data_license": manifest["license"],
        "seed": manifest["seed"],
        # حقول التوافق مع استيراد المقاييس في لوحة المدير (العتبة الافتراضية 50%)
        **{k: default[k] for k in ("accuracy", "precision", "recall", "f1", "false_positive_rate", "false_negative_rate", "confusion_matrix")},
        "threshold": 50.0,
    }

    RESULTS_DIR.mkdir(exist_ok=True)
    (RESULTS_DIR / f"{key}.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    with (RESULTS_DIR / f"{key}_samples.csv").open("w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(["source", "split", "label", "manipulation_probability", "seconds", "error"])
        for r in rows:
            writer.writerow([r["source"], r["split"], r["label"], r.get("probability", ""), r["seconds"], r.get("error", "")])
    write_markdown(key, report)
    return report


def pct(x: float | None) -> str:
    return "—" if x is None else f"{x * 100:.1f}%"


def write_markdown(key: str, r: dict) -> None:
    cm = r["confusion_matrix"]
    lines = [
        f"# نتائج التقييم: {key}", "",
        f"- **بيانات الاختبار:** {r['dataset']} — رخصة: {r['data_license']}",
        f"- **النموذج:** `{r['model']['name']}` الإصدار `{r['model']['version']}`",
        f"- **عدد عينات الاختبار:** {r['n_test']} (حقيقي {r['class_balance_test']['real']} / مزيف {r['class_balance_test']['fake']})، والتحقق: {r['n_validation']}",
        f"- **ملفات فشل تحليلها (مستبعدة من الحساب ومعلنة):** {r['n_failed']}",
        f"- **AUC:** {r['auc']:.3f}" if r["auc"] is not None else "- **AUC:** —",
        f"- **متوسط زمن التحليل للعينة:** {r['seconds_per_sample_mean']} ثانية", "",
        "## المقاييس عند عتبات مختلفة (على بيانات الاختبار)", "",
        "| نقطة التشغيل | العتبة | الدقة | الإحكام | الاستدعاء | F1 | FPR | FNR |", "|---|---|---|---|---|---|---|---|",
    ]
    labels = {"default_50": "افتراضية", "validation_tuned": "مضبوطة على التحقق", "platform_fake_threshold_70": "عتبة المنصة (مزيف ≥ 70)"}
    for name, op in r["operating_points"].items():
        lines.append(f"| {labels[name]} | {op['threshold']:.0f}% | {pct(op['accuracy'])} | {pct(op['precision'])} | {pct(op['recall'])} | {pct(op['f1'])} | {pct(op['false_positive_rate'])} | {pct(op['false_negative_rate'])} |")
    lines += [
        "", "## مصفوفة الارتباك (عتبة 50%؛ الفئة الموجبة = مزيف)", "",
        "| | متوقَّع مزيف | متوقَّع حقيقي |", "|---|---|---|",
        f"| **فعلي مزيف** | TP = {cm['tp']} | FN = {cm['fn']} |",
        f"| **فعلي حقيقي** | FP = {cm['fp']} | TN = {cm['tn']} |", "",
    ]
    t = r["platform_three_class"]
    lines += [
        "## سلوك المنصة بثلاثة تصنيفات (مزيف ≥ 70، حقيقي < 30، وما بينهما غير حاسم)", "",
        f"- نسبة النتائج غير الحاسمة (تحتاج مراجعة بشرية): **{pct(t['uncertain_rate'])}**",
        f"- الدقة على الحالات الحاسمة فقط ({t['decided_samples']} عينة): **{pct(t['accuracy_on_decided'])}**", "",
        "## تحفظات", "",
        f"- {r['data_note']}",
        "- هذه الأرقام تخص هذه العينة فقط، وقد تختلف على محتوى حقيقي مختلف أو تقنيات تزييف أحدث أو ملفات مضغوطة.",
        "- لا تُعد النتائج دليلًا على أن النظام يكتشف كل أنواع التزييف.",
    ]
    (RESULTS_DIR / f"{key}.md").write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate detection models on real test data")
    parser.add_argument("--dataset", choices=sorted(DATASETS), required=True)
    parser.add_argument("--limit", type=int, default=None, help="حدّ لعدد العينات (للتجربة السريعة فقط)")
    args = parser.parse_args()

    report = evaluate(args.dataset, args.limit)
    d = report["operating_points"]["default_50"]
    print(json.dumps({
        "dataset": args.dataset, "n_test": report["n_test"], "failed": report["n_failed"],
        "accuracy": round(d["accuracy"], 4), "precision": round(d["precision"], 4), "recall": round(d["recall"], 4),
        "f1": round(d["f1"], 4), "auc": report["auc"], "confusion_matrix": d["confusion_matrix"],
    }, indent=2))


if __name__ == "__main__":
    main()
