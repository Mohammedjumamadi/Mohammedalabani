"""يقارن نموذجًا مرشّحًا بديلًا بسرعة على عينة من بيانات التقييم قبل اعتماده في السجل الرسمي.

لا يُعدّل سجل الإنتاج (app/registry.py)؛ يحمّل النموذج المرشّح مباشرة ويقيسه على نفس العينات
المستخدمة في evaluate.py، فتكون المقارنة عادلة. النتائج تُطبع فقط ولا تُحفظ، لاختيار الأفضل قبل التبديل.
"""

from __future__ import annotations

import argparse
import json
import sys
import time
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT.parent / "ai-service"))
sys.path.insert(0, str(ROOT))

DATA_DIR = ROOT / "data"


def load_manifest(key: str, limit: int | None):
    """يحمّل العينات؛ عند تحديد limit يأخذ نصفها من كل فئة (manifest.json مرتب: كل الحقيقي ثم كل المزيف)."""
    manifest = json.loads((DATA_DIR / key / "manifest.json").read_text(encoding="utf-8"))
    items = manifest["items"]
    if limit and limit < len(items):
        reals = [i for i in items if i["label"] == 0][: limit // 2]
        fakes = [i for i in items if i["label"] == 1][: limit - len(reals)]
        items = reals + fakes
    return items, manifest["media_type"]


def load_candidate_image(hf_id: str, fake_labels: set[str]):
    from transformers import AutoImageProcessor, AutoModelForImageClassification

    processor = AutoImageProcessor.from_pretrained(hf_id)
    model = AutoModelForImageClassification.from_pretrained(hf_id).eval()
    labels = {int(i): str(n).lower() for i, n in model.config.id2label.items()}
    fake_idx = [i for i, n in labels.items() if n in fake_labels]
    if len(fake_idx) != 1:
        raise SystemExit(f"Ambiguous labels for {hf_id}: {labels}")
    print(f"labels={labels} fake_index={fake_idx[0]}")

    def score(path: str) -> float:
        import torch
        from PIL import Image

        img = Image.open(path).convert("RGB")
        inputs = processor(images=img, return_tensors="pt")
        with torch.no_grad():
            probs = torch.softmax(model(**inputs).logits, dim=-1)[0].numpy()
        return float(probs[fake_idx[0]]) * 100

    return score


def load_candidate_audio(hf_id: str, fake_labels: set[str]):
    from transformers import AutoFeatureExtractor, AutoModelForAudioClassification

    processor = AutoFeatureExtractor.from_pretrained(hf_id)
    model = AutoModelForAudioClassification.from_pretrained(hf_id).eval()
    labels = {int(i): str(n).lower() for i, n in model.config.id2label.items()}
    fake_idx = [i for i, n in labels.items() if n in fake_labels]
    if len(fake_idx) != 1:
        raise SystemExit(f"Ambiguous labels for {hf_id}: {labels}")
    print(f"labels={labels} fake_index={fake_idx[0]} sampling_rate={getattr(processor, 'sampling_rate', 16000)}")
    sr = int(getattr(processor, "sampling_rate", 16000))

    def score(path: str) -> float:
        import torch

        from app.media import decode_audio

        y = decode_audio(path, sr, max_seconds=30)
        if len(y) < sr:
            y = np.pad(y, (0, sr - len(y)))
        # نافذة وسطى بطول 5 ثوانٍ كحد أقصى، لتفادي بطء الطول الكامل ولإبقاء المقارنة متسقة
        window = min(len(y), sr * 5)
        start = max(0, (len(y) - window) // 2)
        segment = y[start:start + window]
        inputs = processor([segment], sampling_rate=sr, return_tensors="pt", padding=True)
        with torch.no_grad():
            probs = torch.softmax(model(**inputs).logits, dim=-1)[0].numpy()
        return float(probs[fake_idx[0]]) * 100

    return score


def evaluate_candidate(key: str, score_fn, limit: int | None) -> None:
    from sklearn.metrics import roc_auc_score

    items, _ = load_manifest(key, limit)
    probs, labels, failed = [], [], 0
    started = time.perf_counter()
    for i, item in enumerate(items, 1):
        try:
            probs.append(score_fn(item["path"]))
            labels.append(item["label"])
        except Exception as exc:  # noqa: BLE001
            failed += 1
            print(f"  fail: {item['source']}: {exc}")
        if i % 20 == 0 or i == len(items):
            print(f"  {i}/{len(items)}")

    probs_arr, labels_arr = np.array(probs), np.array(labels)
    pred = (probs_arr >= 50).astype(int)
    tp = int(((labels_arr == 1) & (pred == 1)).sum())
    fp = int(((labels_arr == 0) & (pred == 1)).sum())
    tn = int(((labels_arr == 0) & (pred == 0)).sum())
    fn = int(((labels_arr == 1) & (pred == 0)).sum())
    n = len(labels_arr)
    acc = (tp + tn) / n if n else 0
    auc = roc_auc_score(labels_arr, probs_arr) if len(set(labels_arr.tolist())) > 1 else None

    print(json.dumps({
        "n": n, "failed": failed, "accuracy": round(acc, 4),
        "auc": round(auc, 4) if auc is not None else None,
        "confusion_matrix": {"tp": tp, "fp": fp, "tn": tn, "fn": fn},
        "seconds_total": round(time.perf_counter() - started, 1),
    }, indent=2))


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", required=True)
    parser.add_argument("--kind", choices=["image", "audio"], required=True)
    parser.add_argument("--hf-id", required=True)
    parser.add_argument("--fake-labels", required=True, help="comma-separated, lowercase")
    parser.add_argument("--limit", type=int, default=80)
    args = parser.parse_args()

    fake_labels = set(args.fake_labels.lower().split(","))
    loader = load_candidate_image if args.kind == "image" else load_candidate_audio
    score_fn = loader(args.hf_id, fake_labels)
    evaluate_candidate(args.dataset, score_fn, args.limit)
