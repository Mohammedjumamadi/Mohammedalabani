"""اختبار دمج نموذج الصوت الأصلي (قوي في الإنجليزية) مع المضبوط على العربية، بمتوسط احتماليهما.

يُقاس على مجموعتين مستقلتين لم يُدرَّب عليهما أي نموذج:
  * evaluation/data/voices_real_vs_tts (إنجليزي، 200 مقطع)
  * قسم test_arad الرسمي من ArAD (عربي، عينة عشوائية ثابتة البذرة)
"""

from __future__ import annotations

import csv
import json
import random
import sys
from pathlib import Path

import numpy as np
from sklearn.metrics import roc_auc_score

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT.parent / "evaluation"))
sys.path.insert(0, str(ROOT.parent / "ai-service"))
from bench_candidate import load_candidate_audio, load_manifest  # noqa: E402

ORIGINAL = "mo-thecreator/Deepfake-audio-detection"
TUNED = str(ROOT / "models" / "audio_voice")


def summarize(name: str, scores: dict[str, np.ndarray], y: np.ndarray) -> dict:
    out = {}
    for key, s in scores.items():
        pred = (s >= 50).astype(int)
        out[key] = {"auc": round(float(roc_auc_score(y, s)), 4), "accuracy_at_50": round(float((pred == y).mean()), 4),
                    "fpr_at_50": round(float(((pred == 1) & (y == 0)).sum() / max(1, (y == 0).sum())), 4),
                    "fnr_at_50": round(float(((pred == 0) & (y == 1)).sum() / max(1, (y == 1).sum())), 4)}
    print(name, json.dumps(out, indent=2))
    return out


def main() -> None:
    orig = load_candidate_audio(ORIGINAL, {"fake"})
    tuned = load_candidate_audio(TUNED, {"fake"})

    items, _ = load_manifest("voices_real_vs_tts", None)
    en = [(i["path"], i["label"]) for i in items]
    with (ROOT / "data" / "prepared" / "audio" / "manifest.csv").open(encoding="utf-8") as f:
        arad = [(str(ROOT / r["path"]), int(r["label"])) for r in csv.DictReader(f) if r["split"] == "test_arad"]
    random.Random(7).shuffle(arad)
    arad = arad[:600]

    results = {}
    for name, rows in (("english_eval", en), ("arabic_test", arad)):
        o = np.array([orig(p) for p, _ in rows]); t = np.array([tuned(p) for p, _ in rows]); y = np.array([l for _, l in rows])
        results[name] = summarize(name, {"original": o, "fine_tuned": t, "ensemble_mean": (o + t) / 2}, y)
    (ROOT / "results" / "ensemble_audio.json").write_text(json.dumps(results, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
