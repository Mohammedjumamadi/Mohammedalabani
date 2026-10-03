"""ينزّل عينة ثابتة ومتوازنة من مجموعات بيانات التقييم ويكتب manifest.json.

الاستخدام:
    python download_samples.py --dataset faces_real_vs_fake --per-class 150
    python download_samples.py --all --per-class 100

العينة حتمية: نرتب أسماء الملفات ثم نختار بذرة ثابتة، فيحصل كل من يعيد التشغيل على العينة نفسها.
تقسيم البيانات: 30% للتحقق (validation) لاختيار العتبة، و70% للاختبار (test) للمقاييس النهائية، بحسب بصمة اسم الملف.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import random
from pathlib import Path

from huggingface_hub import HfApi, hf_hub_download

from eval_datasets import DATASETS

DATA_DIR = Path(__file__).resolve().parent / "data"
SEED = 20260921


def split_of(name: str) -> str:
    """تقسيم ثابت لا يعتمد على ترتيب التنزيل: 30% تحقق و70% اختبار."""
    bucket = int(hashlib.sha256(name.encode()).hexdigest(), 16) % 100
    return "validation" if bucket < 30 else "test"


def download(key: str, per_class: int) -> None:
    spec = DATASETS[key]
    api = HfApi()
    files = api.list_repo_files(spec.repo, repo_type="dataset")

    rng = random.Random(SEED)
    chosen: list[dict] = []
    for label, folder in ((0, spec.real_dir), (1, spec.fake_dir)):
        candidates = sorted(f for f in files if f.startswith(folder + "/") and f.lower().endswith(spec.extensions))
        rng.shuffle(candidates)
        for remote in candidates[:per_class]:
            chosen.append({"remote": remote, "label": label})

    out_dir = DATA_DIR / key
    out_dir.mkdir(parents=True, exist_ok=True)
    manifest = []
    for i, item in enumerate(chosen, 1):
        local = hf_hub_download(spec.repo, item["remote"], repo_type="dataset", local_dir=out_dir / "raw")
        manifest.append({
            "path": str(Path(local).resolve()),
            "label": item["label"],  # 1 = مزيف/مولّد ، 0 = حقيقي
            "split": split_of(item["remote"]),
            "source": f"{spec.repo}:{item['remote']}",
        })
        if i % 25 == 0 or i == len(chosen):
            print(f"  [{key}] {i}/{len(chosen)}", flush=True)

    (out_dir / "manifest.json").write_text(json.dumps({
        "dataset": key, "repo": spec.repo, "media_type": spec.media_type, "license": spec.license,
        "note": spec.note, "seed": SEED, "per_class": per_class, "items": manifest,
    }, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"[{key}] done: {len(manifest)} files -> {out_dir / 'manifest.json'}")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", choices=sorted(DATASETS))
    parser.add_argument("--all", action="store_true")
    parser.add_argument("--per-class", type=int, default=100)
    args = parser.parse_args()

    keys = sorted(DATASETS) if args.all else [args.dataset]
    if not keys or keys == [None]:
        parser.error("specify --dataset or --all")
    for key in keys:
        download(key, args.per_class)


if __name__ == "__main__":
    main()
