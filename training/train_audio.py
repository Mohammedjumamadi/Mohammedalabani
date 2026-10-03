"""ضبط دقيق لنموذج كشف الأصوات المولّدة (wav2vec2) على training/data/prepared/audio/manifest.csv.

نبدأ من النموذج المستخدم حاليًا في الإنتاج، ونقيس على قسم val وعلى قسم الاختبار الرسمي المستقل
لمجموعة ArAD (test_arad) الذي لا يُستخدم في التدريب ولا في اختيار أفضل نسخة.

مثال:
  python train_audio.py --base mo-thecreator/Deepfake-audio-detection --fake-labels fake --out models/audio_voice
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import random
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import soundfile as sf
import torch
from torch.utils.data import DataLoader, Dataset

ROOT = Path(__file__).resolve().parent
AMP = False  # يُضبط من --fp16
SR = 16_000


class ManifestAudio(Dataset):
    def __init__(self, rows: list[dict], seconds: float, train: bool, fake_idx: int, real_idx: int):
        self.rows, self.n, self.train, self.fake_idx, self.real_idx = rows, int(seconds * SR), train, fake_idx, real_idx

    def __len__(self) -> int:
        return len(self.rows)

    def __getitem__(self, i: int):
        row = self.rows[i]
        wav, _ = sf.read(ROOT / row["path"], dtype="float32")
        if len(wav) > self.n:
            start = random.randint(0, len(wav) - self.n) if self.train else (len(wav) - self.n) // 2
            wav = wav[start:start + self.n]
        elif len(wav) < self.n:
            wav = np.pad(wav, (0, self.n - len(wav)))
        if self.train:
            wav = wav * random.uniform(0.5, 1.2)                     # تغيير مستوى الصوت
            if random.random() < 0.3:                                # ضوضاء خفيفة كما في التسجيلات الحقيقية
                wav = wav + np.random.normal(0, random.uniform(0.001, 0.008), len(wav)).astype(np.float32)
        wav = (wav - wav.mean()) / (wav.std() + 1e-7)                 # نفس تطبيع Wav2Vec2FeatureExtractor
        target = self.fake_idx if int(row["label"]) == 1 else self.real_idx
        return torch.from_numpy(wav.astype(np.float32)), target, int(row["label"])


@torch.no_grad()
def evaluate(model, loader, fake_idx: int, device: str) -> dict:
    from sklearn.metrics import roc_auc_score

    model.eval()
    scores, labels = [], []
    for x, _, y in loader:
        with torch.autocast(device_type="cuda", dtype=torch.float16, enabled=device == "cuda" and AMP):
            logits = model(input_values=x.to(device, non_blocking=True)).logits
        scores.append(torch.softmax(logits.float(), dim=-1)[:, fake_idx].cpu().numpy())
        labels.append(y.numpy())
    s, y = np.concatenate(scores), np.concatenate(labels)
    pred = (s >= 0.5).astype(int)
    return {"auc": float(roc_auc_score(y, s)) if len(set(y.tolist())) == 2 else float("nan"),
            "accuracy": float((pred == y).mean()),
            "fpr": float(((pred == 1) & (y == 0)).sum() / max(1, (y == 0).sum())),
            "fnr": float(((pred == 0) & (y == 1)).sum() / max(1, (y == 1).sum())), "n": int(len(y))}


def freeze_lower_layers(model, ratio: float) -> None:
    """يجمّد التضمين والنصف الأدنى (بحسب ratio) من طبقات المُشفّر، ويُبقي الطبقات العليا والمصنِّف قابلة للتدريب.
    على كرت بلا Tensor Cores (مثل T600) يوفّر هذا نحو نصف حساب الانتشار العكسي مع الحفاظ على معظم الفائدة."""
    import re as _re

    if ratio <= 0:
        return
    indices = [int(m.group(1)) for n, _ in model.named_parameters() if (m := _re.search(r"(?:encoder\.)?\blayers?\.(\d+)\.", n))]
    if not indices:
        return
    cutoff = int((max(indices) + 1) * ratio)
    frozen = 0
    for name, param in model.named_parameters():
        m = _re.search(r"(?:encoder\.)?\blayers?\.(\d+)\.", name)
        if ".embeddings." in f".{name}" or name.startswith(("embeddings.",)) or (m and int(m.group(1)) < cutoff):
            param.requires_grad = False
            frozen += param.numel()
    total = sum(p.numel() for p in model.parameters())
    print(f"frozen {frozen / 1e6:.1f}M of {total / 1e6:.1f}M parameters (encoder layers < {cutoff})")


def keep_awake() -> None:
    """يطلب من Windows عدم الدخول في السكون التلقائي ما دام التدريب يعمل (كما تفعل مشغلات الفيديو).
    لا يغيّر إعدادات الطاقة، وينتهي أثره تلقائيًا بانتهاء العملية."""
    import sys

    if sys.platform == "win32":
        import ctypes

        ctypes.windll.kernel32.SetThreadExecutionState(0x80000000 | 0x00000001)  # ES_CONTINUOUS | ES_SYSTEM_REQUIRED


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", required=True)
    parser.add_argument("--fake-labels", required=True)
    parser.add_argument("--out", required=True)
    parser.add_argument("--epochs", type=int, default=3)
    parser.add_argument("--seconds", type=float, default=4.0)
    parser.add_argument("--batch", type=int, default=8)
    parser.add_argument("--accum", type=int, default=4)
    parser.add_argument("--lr", type=float, default=3e-5)
    parser.add_argument("--workers", type=int, default=4)
    parser.add_argument("--samples-per-epoch", type=int, default=0, help="balanced samples drawn per epoch (0 = size of train split)")
    parser.add_argument("--fp16", action="store_true", help="mixed precision (slower on GPUs without tensor cores, e.g. T600)")
    parser.add_argument("--freeze", type=float, default=0.5, help="fraction of lower encoder layers to freeze")
    parser.add_argument("--limit", type=int, default=0)
    args = parser.parse_args()

    from transformers import AutoFeatureExtractor, AutoModelForAudioClassification

    keep_awake()
    global AMP
    AMP = args.fp16
    random.seed(1337); np.random.seed(1337); torch.manual_seed(1337)
    device = "cuda" if torch.cuda.is_available() else "cpu"
    print(f"device={device} {torch.cuda.get_device_name(0) if device == 'cuda' else ''}")

    extractor = AutoFeatureExtractor.from_pretrained(args.base)
    model = AutoModelForAudioClassification.from_pretrained(args.base)
    labels = {int(k): str(v).lower() for k, v in model.config.id2label.items()}
    fake = [i for i, n in labels.items() if n in {x.strip() for x in args.fake_labels.split(",")}]
    if len(fake) != 1 or len(labels) != 2:
        raise SystemExit(f"Unexpected labels {labels}")
    fake_idx, real_idx = fake[0], 1 - fake[0]
    print(f"labels={labels} fake_idx={fake_idx}")

    with (ROOT / "data" / "prepared" / "audio" / "manifest.csv").open(encoding="utf-8") as f:
        rows = list(csv.DictReader(f))
    by = {s: [r for r in rows if r["split"] == s] for s in ("train", "val", "test_arad")}
    if args.limit:
        for k in by:
            random.shuffle(by[k])
        by = {"train": by["train"][: args.limit], "val": by["val"][: max(32, args.limit // 5)], "test_arad": by["test_arad"][: max(32, args.limit // 5)]}
    print({k: len(v) for k, v in by.items()})

    def loader(split: str, train: bool) -> DataLoader:
        dataset = ManifestAudio(by[split], args.seconds, train, fake_idx, real_idx)
        sampler = None
        if train:
            # البيانات غير متوازنة (المزيف أكثر بكثير): نسحب الفئتين بالتساوي حتى لا يتعلم النموذج قول «مزيف» دائمًا
            counts = {0: sum(1 for r in by[split] if r["label"] == "0"), 1: sum(1 for r in by[split] if r["label"] == "1")}
            weights = [1.0 / counts[int(r["label"])] for r in by[split]]
            sampler = torch.utils.data.WeightedRandomSampler(weights, num_samples=args.samples_per_epoch or len(weights), replacement=True)
            print(f"balanced sampling: real={counts[0]} fake={counts[1]}")
        return DataLoader(dataset, batch_size=args.batch if train else args.batch * 2, sampler=sampler,
                          num_workers=args.workers, pin_memory=True, drop_last=train, persistent_workers=train and args.workers > 0)

    train_dl, val_dl, test_dl = loader("train", True), loader("val", False), loader("test_arad", False)

    if hasattr(model, "freeze_feature_encoder"):
        model.freeze_feature_encoder()          # الطبقات الالتفافية الأولى ثابتة: تدريب أسرع وأكثر استقرارًا
    if getattr(model, "supports_gradient_checkpointing", False):
        model.gradient_checkpointing_enable()
    freeze_lower_layers(model, args.freeze)
    model.to(device)

    optim = torch.optim.AdamW([p for p in model.parameters() if p.requires_grad], lr=args.lr, weight_decay=0.01)
    steps = math.ceil(len(train_dl) / args.accum) * args.epochs
    warmup = max(1, int(steps * 0.1))
    sched = torch.optim.lr_scheduler.LambdaLR(optim, lambda s: min(1.0, (s + 1) / warmup) * 0.5 * (1 + math.cos(math.pi * min(1.0, s / steps))))
    scaler = torch.amp.GradScaler("cuda", enabled=device == "cuda" and AMP)
    loss_fn = torch.nn.CrossEntropyLoss(label_smoothing=0.05)

    baseline = {"val": evaluate(model, val_dl, fake_idx, device), "test_arad": evaluate(model, test_dl, fake_idx, device)}
    print(f"baseline: {json.dumps(baseline)}", flush=True)

    out = ROOT / args.out
    best, history = -1.0, []
    for epoch in range(1, args.epochs + 1):
        model.train()
        t0, running = time.time(), 0.0
        optim.zero_grad(set_to_none=True)
        for step, (x, target, _) in enumerate(train_dl, 1):
            with torch.autocast(device_type="cuda", dtype=torch.float16, enabled=device == "cuda" and AMP):
                loss = loss_fn(model(input_values=x.to(device, non_blocking=True)).logits.float(), target.to(device)) / args.accum
            scaler.scale(loss).backward()
            running += loss.item() * args.accum
            if step % args.accum == 0 or step == len(train_dl):
                scaler.unscale_(optim)
                torch.nn.utils.clip_grad_norm_(model.parameters(), 1.0)
                scaler.step(optim); scaler.update(); sched.step()
                optim.zero_grad(set_to_none=True)
            if step % 100 == 0:
                print(f"  epoch {epoch} step {step}/{len(train_dl)} loss={running / step:.4f} {(time.time() - t0) / step:.2f}s/step", flush=True)
        metrics = {"epoch": epoch, "train_loss": running / len(train_dl), "minutes": round((time.time() - t0) / 60, 1),
                   "val": evaluate(model, val_dl, fake_idx, device)}
        history.append(metrics)
        print(f"epoch {epoch}: {json.dumps(metrics)}", flush=True)
        if metrics["val"]["auc"] > best:
            best = metrics["val"]["auc"]
            out.mkdir(parents=True, exist_ok=True)
            model.save_pretrained(out, safe_serialization=True)
            extractor.save_pretrained(out)
            print(f"  saved best -> {out}")

    # الاختبار المستقل يُقاس مرة واحدة فقط على أفضل نسخة محفوظة
    best_model = AutoModelForAudioClassification.from_pretrained(out).to(device)
    test = evaluate(best_model, test_dl, fake_idx, device)
    print(f"held-out ArAD test (best checkpoint): {json.dumps(test)}")

    meta = {
        "task": "audio", "base_model": args.base, "fake_label": labels[fake_idx],
        "trained_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "train_samples": len(by["train"]), "val_samples": len(by["val"]), "test_arad_samples": len(by["test_arad"]),
        "epochs": args.epochs, "lr": args.lr, "clip_seconds": args.seconds, "effective_batch": args.batch * args.accum,
        "baseline": baseline, "history": history, "best_val_auc": best, "test_arad": test,
        "data_sources": sorted({r["source"].split(":", 1)[0] for r in rows}),
    }
    (out / "training_meta.json").write_text(json.dumps(meta, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"done. best val AUC={best:.4f}")


if __name__ == "__main__":
    main()
