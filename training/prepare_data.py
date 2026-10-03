"""تجهيز بيانات التدريب من مصادر عامة على Hugging Face، مع ضمان عدم تسرّب بيانات التقييم.

المصادر (راجع training/README.md للتراخيص):
  * صور مولّدة مقابل حقيقية : Hemg/AI-Generated-vs-Real-Images-Datasets (parquet، 152,710 صورة)
  * وجوه مزيفة مقابل حقيقية : OpenRL/DeepFakeFace (wiki = حقيقي؛ inpainting/insight/text2img = مزيف)
  * أصوات مزيفة مقابل حقيقية: DeepFake-Audio-Rangers/Arabic_Audio_Deepfake (عربي) + garystafford/deepfake-audio-detection

قواعد منع التسرّب (أساس نزاهة القياس):
  1) أي صورة تطابق بصمتها (SHA-256 للبايتات أو aHash للبكسلات) صورةً في evaluation/data تُستبعد.
  2) مقاطع garystafford مقطوعة من تسجيلات طويلة؛ نستبعد التسجيل كاملًا (كل أجزائه) إن ظهر أي جزء منه
     في بيانات التقييم، حتى لا يتعلّم النموذج صوت المتحدث نفسه ثم «يتعرّف» عليه عند القياس.
  3) بيانات التقييم نفسها لا تُقرأ للتدريب إطلاقًا؛ تُقرأ فقط لبناء قوائم الاستبعاد.

المخرجات: training/data/prepared/<task>/ (ملفات + manifest.csv بأعمدة path,label,source,split)
حيث label = 1 يعني مزيف/مولّد، و split = train | val.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import io
import json
import random
import re
import zipfile
from collections import Counter
from pathlib import Path

import numpy as np
from PIL import Image, ImageFile

ImageFile.LOAD_TRUNCATED_IMAGES = True
Image.MAX_IMAGE_PIXELS = 100_000_000

ROOT = Path(__file__).resolve().parent
PROJECT = ROOT.parent
EVAL_DIR = PROJECT / "evaluation" / "data"
HF_DIR = ROOT / "data" / "hf"
OUT_DIR = ROOT / "data" / "prepared"
SEED = 1337
IMAGE_SIDE = 256          # الضلع الأقصر بعد التصغير (التدريب يقتطع 224)
AUDIO_SR = 16_000
AUDIO_MAX_SECONDS = 8.0


# ---------------------------------------------------------------- أدوات مشتركة
def ahash(img: Image.Image) -> str:
    """بصمة متوسطة 16×16 (تلتقط الصورة نفسها بعد إعادة الحفظ أو تغيير الصيغة)."""
    g = np.asarray(img.convert("L").resize((16, 16), Image.BILINEAR), dtype=np.float32)
    return np.packbits(g.flatten() > g.mean()).tobytes().hex()


def save_image(img: Image.Image, path: Path) -> None:
    img = img.convert("RGB")
    w, h = img.size
    scale = IMAGE_SIDE / min(w, h)
    if scale < 1:
        img = img.resize((max(1, round(w * scale)), max(1, round(h * scale))), Image.BICUBIC)
    path.parent.mkdir(parents=True, exist_ok=True)
    # كل الصور (حقيقية ومولّدة) تُحفظ JPEG بالجودة نفسها: لا يتعلم النموذج «صيغة الملف» بدل المحتوى
    img.save(path, "JPEG", quality=95)


def write_manifest(task: str, rows: list[dict]) -> None:
    out = OUT_DIR / task / "manifest.csv"
    out.parent.mkdir(parents=True, exist_ok=True)
    with out.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=["path", "label", "source", "split"])
        writer.writeheader()
        writer.writerows(rows)
    counts = Counter((r["split"], r["label"]) for r in rows)
    print(f"[{task}] {len(rows)} rows -> {out}")
    for (split, label), n in sorted(counts.items()):
        print(f"    {split:5s} label={label} ({'fake' if label == 1 else 'real'}): {n}")


def split_rows(rows: list[dict], val_ratio: float, rng: random.Random) -> list[dict]:
    """تقسيم طبقي (لكل فئة على حدة) إلى train/val."""
    for label in (0, 1):
        group = [r for r in rows if r["label"] == label]
        rng.shuffle(group)
        n_val = max(1, int(len(group) * val_ratio))
        for i, r in enumerate(group):
            r["split"] = "val" if i < n_val else "train"
    return rows


def eval_items(dataset: str) -> list[dict]:
    manifest = EVAL_DIR / dataset / "manifest.json"
    if not manifest.exists():
        return []
    return json.loads(manifest.read_text(encoding="utf-8"))["items"]


# ---------------------------------------------------------------- الصور المولّدة
def prepare_ai_images(per_class: int, rng: random.Random) -> None:
    import pyarrow.parquet as pq
    from huggingface_hub import snapshot_download

    repo = "Hemg/AI-Generated-vs-Real-Images-Datasets"
    local = Path(snapshot_download(repo, repo_type="dataset", allow_patterns=["data/*.parquet"], local_dir=HF_DIR / "hemg"))

    # بصمات صور التقييم (ai_art_vs_real) لاستبعادها
    ex_sha, ex_hash = set(), set()
    for item in eval_items("ai_art_vs_real"):
        p = Path(item["path"])
        if p.exists():
            data = p.read_bytes()
            ex_sha.add(hashlib.sha256(data).hexdigest())
            try:
                ex_hash.add(ahash(Image.open(io.BytesIO(data))))
            except Exception:  # noqa: BLE001
                pass
    print(f"[ai_images] excluding {len(ex_sha)} evaluation images by fingerprint")

    out = OUT_DIR / "ai_images"
    # في هذا المستودع: 0 = AiArtData (مولّد)، 1 = RealArt (حقيقي) — نحوّلها إلى 1 = مزيف
    candidates = {0: [], 1: []}
    for pf in sorted(local.glob("data/*.parquet")):
        table = pq.read_table(pf, columns=["label"])
        for i, lbl in enumerate(table.column("label").to_pylist()):
            candidates[1 if lbl == 0 else 0].append((pf, i))
    for k in candidates:
        rng.shuffle(candidates[k])

    rows, skipped_eval, skipped_bad = [], 0, 0
    for label in (0, 1):
        taken = 0
        by_file: dict[Path, list[int]] = {}
        # نأخذ هامشًا إضافيًا للتعويض عن المستبعد والتالف
        for pf, i in candidates[label][: int(per_class * 1.15)]:
            by_file.setdefault(pf, []).append(i)
        for pf, indices in by_file.items():
            if taken >= per_class:
                break
            images = pq.read_table(pf, columns=["image"]).column("image").to_pylist()
            for i in indices:
                if taken >= per_class:
                    break
                data = images[i]["bytes"]
                try:
                    img = Image.open(io.BytesIO(data))
                    img.load()
                except Exception:  # noqa: BLE001
                    skipped_bad += 1
                    continue
                if hashlib.sha256(data).hexdigest() in ex_sha or ahash(img) in ex_hash:
                    skipped_eval += 1
                    continue
                path = out / ("fake" if label else "real") / f"{pf.stem[:24]}_{i}.jpg"
                save_image(img, path)
                rows.append({"path": str(path.relative_to(ROOT)), "label": label, "source": f"{repo}:{pf.name}#{i}", "split": ""})
                taken += 1
    print(f"[ai_images] skipped: {skipped_eval} matched evaluation, {skipped_bad} unreadable")
    write_manifest("ai_images", split_rows(rows, 0.1, rng))


# ---------------------------------------------------------------- الوجوه المزيفة
def prepare_faces(per_class: int, rng: random.Random) -> None:
    from huggingface_hub import hf_hub_download

    repo = "OpenRL/DeepFakeFace"
    zips = {"wiki": 0, "inpainting": 1, "insight": 1, "text2img": 1}
    per_zip = {"wiki": per_class, "inpainting": per_class // 3, "insight": per_class // 3, "text2img": per_class - 2 * (per_class // 3)}
    out = OUT_DIR / "faces"
    rows = []
    for name, label in zips.items():
        zpath = hf_hub_download(repo, f"{name}.zip", repo_type="dataset", local_dir=HF_DIR / "deepfakeface")
        with zipfile.ZipFile(zpath) as zf:
            members = [m for m in zf.namelist() if m.lower().endswith((".jpg", ".jpeg", ".png"))]
            rng.shuffle(members)
            taken = 0
            for m in members:
                if taken >= per_zip[name]:
                    break
                try:
                    img = Image.open(io.BytesIO(zf.read(m)))
                    img.load()
                except Exception:  # noqa: BLE001
                    continue
                safe = re.sub(r"[^A-Za-z0-9_.-]", "_", m)[-80:]
                path = out / name / f"{safe.rsplit('.', 1)[0]}.jpg"
                save_image(img, path)
                rows.append({"path": str(path.relative_to(ROOT)), "label": label, "source": f"{repo}:{name}.zip/{m}", "split": ""})
                taken += 1
        print(f"[faces] {name}: {taken}")
    # ملاحظة: بيانات تقييم الوجوه (Sowaiba01/Deepfake) من مصدر مختلف تمامًا، فالقياس عليها يقيس التعميم الحقيقي
    write_manifest("faces", split_rows(rows, 0.1, rng))


# ---------------------------------------------------------------- الأصوات المزيفة
def recording_key(filename: str) -> str:
    """yt_0000_part_073.flac / el_0010_c_part_009.flac -> yt_0000 / el_0010 (التسجيل الأصلي)."""
    stem = Path(filename).stem
    return re.split(r"_(?:c_|p2_)?part_", stem)[0]


def decode_audio(data: bytes) -> np.ndarray | None:
    import librosa
    import soundfile as sf

    try:
        wav, sr = sf.read(io.BytesIO(data), dtype="float32", always_2d=True)
    except Exception:  # noqa: BLE001
        try:
            wav, sr = librosa.load(io.BytesIO(data), sr=None, mono=False)
            wav = np.atleast_2d(wav).T
        except Exception:  # noqa: BLE001
            return None
    wav = wav.mean(axis=1)
    if sr != AUDIO_SR:
        wav = librosa.resample(wav, orig_sr=sr, target_sr=AUDIO_SR)
    if len(wav) < AUDIO_SR:  # أقل من ثانية: غير مفيد
        return None
    return wav[: int(AUDIO_SR * AUDIO_MAX_SECONDS)]


def prepare_audio(rng: random.Random) -> None:
    import pyarrow.parquet as pq
    import soundfile as sf
    from huggingface_hub import snapshot_download

    out = OUT_DIR / "audio"
    rows = []

    # 1) العربي ArAD: 0 = fake، 1 = real ← نحوّل إلى 1 = مزيف. نستخدم قسم train للتدريب وقسم test كاختبار مستقل
    repo = "DeepFake-Audio-Rangers/Arabic_Audio_Deepfake"
    local = Path(snapshot_download(repo, repo_type="dataset", allow_patterns=["data/*.parquet"], local_dir=HF_DIR / "arad"))
    for pf in sorted(local.glob("data/*.parquet")):
        official = "test" if pf.name.startswith("test") else "train"
        table = pq.read_table(pf, columns=["audio", "label"]).to_pylist()
        for i, row in enumerate(table):
            wav = decode_audio(row["audio"]["bytes"])
            if wav is None:
                continue
            label = 1 if row["label"] == 0 else 0
            path = out / "arad" / official / f"{pf.stem[:20]}_{i}.wav"
            path.parent.mkdir(parents=True, exist_ok=True)
            sf.write(path, wav, AUDIO_SR, subtype="PCM_16")
            rows.append({"path": str(path.relative_to(ROOT)), "label": label, "source": f"{repo}:{pf.name}#{i}", "split": "test_arad" if official == "test" else ""})
    print(f"[audio] ArAD rows: {len(rows)}")

    # 2) garystafford (إنجليزي): 0 = real، 1 = fake — مع استبعاد كل تسجيل ظهر في بيانات التقييم
    repo = "garystafford/deepfake-audio-detection"
    excluded = {recording_key(Path(i["source"].split(":", 1)[1]).name) for i in eval_items("voices_real_vs_tts")}
    print(f"[audio] excluding {len(excluded)} whole recordings that appear in evaluation data")
    local = Path(snapshot_download(repo, repo_type="dataset", allow_patterns=["data/*.parquet"], local_dir=HF_DIR / "garystafford"))
    kept = skipped = 0
    for pf in sorted(local.glob("data/*.parquet")):
        for i, row in enumerate(pq.read_table(pf, columns=["audio", "label"]).to_pylist()):
            name = Path(row["audio"].get("path") or "").name
            if not name or recording_key(name) in excluded:
                skipped += 1
                continue
            wav = decode_audio(row["audio"]["bytes"])
            if wav is None:
                continue
            path = out / "garystafford" / f"{Path(name).stem}.wav"
            path.parent.mkdir(parents=True, exist_ok=True)
            sf.write(path, wav, AUDIO_SR, subtype="PCM_16")
            rows.append({"path": str(path.relative_to(ROOT)), "label": int(row["label"]), "source": f"{repo}:{name}", "split": ""})
            kept += 1
    print(f"[audio] garystafford kept {kept}, excluded {skipped} (recordings used in evaluation)")

    trainable = [r for r in rows if r["split"] == ""]
    split_rows(trainable, 0.1, rng)
    write_manifest("audio", rows)


def main() -> None:
    parser = argparse.ArgumentParser(description="Prepare leak-free training data")
    parser.add_argument("--task", choices=["ai_images", "faces", "audio", "all"], default="all")
    parser.add_argument("--image-per-class", type=int, default=20_000)
    parser.add_argument("--face-per-class", type=int, default=12_000)
    args = parser.parse_args()

    tasks = ["ai_images", "faces", "audio"] if args.task == "all" else [args.task]
    for task in tasks:
        rng = random.Random(SEED)
        if task == "ai_images":
            prepare_ai_images(args.image_per_class, rng)
        elif task == "faces":
            prepare_faces(args.face_per_class, rng)
        else:
            prepare_audio(rng)


if __name__ == "__main__":
    main()
