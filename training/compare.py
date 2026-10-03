"""مقارنة النموذج الأصلي بالنسخة المضبوطة على بيانات التقييم المستقلة (evaluation/data)، وترقية الأفضل.

طريقة القياس نفسها لكلا النموذجين (دوال evaluation/bench_candidate.py)، على العينات نفسها:
  * AUC ودقة عند العتبة 50% على كل العينات،
  * عتبة تُختار على قسم validation ثم تُقاس الدقة بها على قسم test (بلا تفاؤل).

قاعدة الترقية (--promote): تُنسخ النسخة المضبوطة إلى ai-service/models/<key>/ فقط إذا
  AUC(المضبوط) ≥ AUC(الأصلي) + 0.02  و  دقة test(المضبوط) ≥ دقة test(الأصلي).
"""

from __future__ import annotations

import argparse
import json
import shutil
import sys
from datetime import datetime, timezone
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent
PROJECT = ROOT.parent
sys.path.insert(0, str(PROJECT / "evaluation"))
sys.path.insert(0, str(PROJECT / "ai-service"))

from bench_candidate import load_candidate_audio, load_candidate_image, load_manifest  # noqa: E402

TARGETS = {
    "image_gen": {"dataset": "ai_art_vs_real", "kind": "image", "base": "umm-maybe/AI-image-detector", "fake": {"artificial"}},
    "image_face": {"dataset": "faces_real_vs_fake", "kind": "image", "base": "prithivMLmods/Deep-Fake-Detector-v2-Model", "fake": {"deepfake"}},
    "audio_voice": {"dataset": "voices_real_vs_tts", "kind": "audio", "base": "mo-thecreator/Deepfake-audio-detection", "fake": {"fake"}},
}


def metrics(scores: np.ndarray, labels: np.ndarray, splits: np.ndarray) -> dict:
    from sklearn.metrics import roc_auc_score

    pred = (scores >= 50).astype(int)
    val, test = splits == "validation", splits == "test"
    # أفضل عتبة على validation فقط، ثم تُطبَّق على test
    grid = np.arange(1, 100)
    best_t = max(grid, key=lambda t: ((scores[val] >= t).astype(int) == labels[val]).mean()) if val.any() else 50
    test_pred = (scores[test] >= best_t).astype(int)
    return {
        "n": int(len(labels)),
        "auc": round(float(roc_auc_score(labels, scores)), 4),
        "accuracy_at_50": round(float((pred == labels).mean()), 4),
        "fpr_at_50": round(float(((pred == 1) & (labels == 0)).sum() / max(1, (labels == 0).sum())), 4),
        "fnr_at_50": round(float(((pred == 0) & (labels == 1)).sum() / max(1, (labels == 1).sum())), 4),
        "threshold_from_validation": int(best_t),
        "test_accuracy": round(float((test_pred == labels[test]).mean()), 4) if test.any() else None,
    }


def score_all(key: str, model_ref: str) -> dict:
    spec = TARGETS[key]
    loader = load_candidate_image if spec["kind"] == "image" else load_candidate_audio
    score_fn = loader(model_ref, spec["fake"])
    items, _ = load_manifest(spec["dataset"], None)
    s, y, sp = [], [], []
    for item in items:
        try:
            s.append(score_fn(item["path"]))
        except Exception as exc:  # noqa: BLE001
            print(f"  skip {item['source']}: {exc}")
            continue
        y.append(item["label"]); sp.append(item.get("split", "test"))
    return metrics(np.array(s), np.array(y), np.array(sp))


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--only", choices=sorted(TARGETS), nargs="*")
    parser.add_argument("--promote", action="store_true")
    args = parser.parse_args()

    results = {}
    for key in args.only or TARGETS:
        trained = ROOT / "models" / key
        if not (trained / "model.safetensors").exists():
            print(f"[{key}] no fine-tuned model yet, skipping")
            continue
        print(f"[{key}] scoring original ...")
        original = score_all(key, TARGETS[key]["base"])
        print(f"[{key}] scoring fine-tuned ...")
        tuned = score_all(key, str(trained))
        better = tuned["auc"] >= original["auc"] + 0.02 and (tuned["test_accuracy"] or 0) >= (original["test_accuracy"] or 0)
        results[key] = {"dataset": TARGETS[key]["dataset"], "original": original, "fine_tuned": tuned, "promote": better}
        print(json.dumps(results[key], indent=2))

        if args.promote and better:
            dest = PROJECT / "ai-service" / "models" / key
            if dest.exists():
                shutil.rmtree(dest)
            shutil.copytree(trained, dest)
            meta_path = dest / "training_meta.json"
            meta = json.loads(meta_path.read_text(encoding="utf-8")) if meta_path.exists() else {}
            meta["independent_evaluation"] = results[key]
            meta_path.write_text(json.dumps(meta, indent=2, ensure_ascii=False), encoding="utf-8")
            print(f"[{key}] PROMOTED -> {dest}")
        elif args.promote:
            print(f"[{key}] not promoted (did not clearly beat the original)")

    out = ROOT / "results" / "compare.json"
    out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps({"generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"), "results": results}, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"saved {out}")


if __name__ == "__main__":
    main()
