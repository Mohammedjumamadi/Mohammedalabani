"""ضبط دقيق (fine-tuning) لنموذج صور على بيانات training/data/prepared/<task>/manifest.csv.

نبدأ من النموذج المستخدم حاليًا في الإنتاج (لا تدريب من الصفر)، ونحافظ على أسماء تسمياته حتى
يبقى متوافقًا مع ai-service/app/registry.py. يُحفظ أفضل نموذج بحسب AUC على قسم val فقط.

أمثلة:
  python train_image.py --task ai_images --base umm-maybe/AI-image-detector   --fake-labels artificial --out models/image_gen
  python train_image.py --task faces     --base prithivMLmods/Deep-Fake-Detector-v2-Model --fake-labels deepfake --out models/image_face
"""

from __future__ import annotations

import argparse
import csv
import io
import json
import math
import random
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import torch
from PIL import Image, ImageFilter
from torch.utils.data import DataLoader, Dataset
from torchvision import transforms

ROOT = Path(__file__).resolve().parent
AMP = False  # يُضبط من --fp16


class RandomJpeg:
    """إعادة ضغط JPEG عشوائية: الصور على الإنترنت مضغوطة غالبًا، ولا نريد نموذجًا ينهار أمام الضغط."""

    def __init__(self, p: float = 0.5, quality: tuple[int, int] = (45, 95)):
        self.p, self.quality = p, quality

    def __call__(self, img: Image.Image) -> Image.Image:
        if random.random() > self.p:
            return img
        buf = io.BytesIO()
        img.save(buf, "JPEG", quality=random.randint(*self.quality))
        return Image.open(io.BytesIO(buf.getvalue())).convert("RGB")


class RandomBlur:
    def __init__(self, p: float = 0.1):
        self.p = p

    def __call__(self, img: Image.Image) -> Image.Image:
        return img.filter(ImageFilter.GaussianBlur(random.uniform(0.3, 1.2))) if random.random() < self.p else img


class ManifestImages(Dataset):
    def __init__(self, rows: list[dict], transform, fake_idx: int, real_idx: int):
        self.rows, self.transform, self.fake_idx, self.real_idx = rows, transform, fake_idx, real_idx

    def __len__(self) -> int:
        return len(self.rows)

    def __getitem__(self, i: int):
        row = self.rows[i]
        img = Image.open(ROOT / row["path"]).convert("RGB")
        target = self.fake_idx if int(row["label"]) == 1 else self.real_idx
        return self.transform(img), target, int(row["label"])


def auc_score(scores: np.ndarray, labels: np.ndarray) -> float:
    from sklearn.metrics import roc_auc_score

    return float(roc_auc_score(labels, scores)) if len(set(labels.tolist())) == 2 else float("nan")


@torch.no_grad()
def evaluate(model, loader, fake_idx: int, device: str) -> dict:
    model.eval()
    scores, labels = [], []
    for x, _, y in loader:
        with torch.autocast(device_type="cuda", dtype=torch.float16, enabled=device == "cuda" and AMP):
            logits = model(pixel_values=x.to(device, non_blocking=True)).logits
        scores.append(torch.softmax(logits.float(), dim=-1)[:, fake_idx].cpu().numpy())
        labels.append(y.numpy())
    s, y = np.concatenate(scores), np.concatenate(labels)
    pred = (s >= 0.5).astype(int)
    return {"auc": auc_score(s, y), "accuracy": float((pred == y).mean()),
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
    parser.add_argument("--task", required=True, choices=["ai_images", "faces"])
    parser.add_argument("--base", required=True, help="Hugging Face id of the model to fine-tune")
    parser.add_argument("--fake-labels", required=True, help="comma-separated lowercase label names meaning fake")
    parser.add_argument("--out", required=True)
    parser.add_argument("--epochs", type=int, default=3)
    parser.add_argument("--batch", type=int, default=16)
    parser.add_argument("--accum", type=int, default=4, help="gradient accumulation steps (effective batch = batch*accum)")
    parser.add_argument("--lr", type=float, default=2e-5)
    parser.add_argument("--workers", type=int, default=4)
    parser.add_argument("--fp16", action="store_true", help="mixed precision (slower on GPUs without tensor cores, e.g. T600)")
    parser.add_argument("--freeze", type=float, default=0.5, help="fraction of lower encoder layers to freeze")
    parser.add_argument("--limit", type=int, default=0, help="for smoke tests only")
    args = parser.parse_args()

    from transformers import AutoImageProcessor, AutoModelForImageClassification

    keep_awake()
    global AMP
    AMP = args.fp16
    random.seed(1337); np.random.seed(1337); torch.manual_seed(1337)
    device = "cuda" if torch.cuda.is_available() else "cpu"
    print(f"device={device} {torch.cuda.get_device_name(0) if device == 'cuda' else ''}")

    processor = AutoImageProcessor.from_pretrained(args.base)
    model = AutoModelForImageClassification.from_pretrained(args.base)
    labels = {int(k): str(v).lower() for k, v in model.config.id2label.items()}
    fake_names = {x.strip() for x in args.fake_labels.split(",")}
    fake = [i for i, n in labels.items() if n in fake_names]
    if len(fake) != 1 or len(labels) != 2:
        raise SystemExit(f"Unexpected labels {labels}")
    fake_idx, real_idx = fake[0], 1 - fake[0]
    print(f"labels={labels} fake_idx={fake_idx}")

    size = processor.size.get("height") or processor.size.get("shortest_edge") or 224
    norm = transforms.Normalize(processor.image_mean, processor.image_std)
    train_tf = transforms.Compose([
        RandomJpeg(0.5), RandomBlur(0.1),
        transforms.RandomResizedCrop(size, scale=(0.6, 1.0), ratio=(0.8, 1.25)),
        transforms.RandomHorizontalFlip(),
        transforms.ColorJitter(0.1, 0.1, 0.05),
        transforms.ToTensor(), norm,
    ])
    eval_tf = transforms.Compose([transforms.Resize(size), transforms.CenterCrop(size), transforms.ToTensor(), norm])

    with (ROOT / "data" / "prepared" / args.task / "manifest.csv").open(encoding="utf-8") as f:
        rows = list(csv.DictReader(f))
    train_rows = [r for r in rows if r["split"] == "train"]
    val_rows = [r for r in rows if r["split"] == "val"]
    if args.limit:
        random.shuffle(train_rows); random.shuffle(val_rows)
        train_rows, val_rows = train_rows[: args.limit], val_rows[: max(32, args.limit // 5)]
    print(f"train={len(train_rows)} val={len(val_rows)}")

    train_dl = DataLoader(ManifestImages(train_rows, train_tf, fake_idx, real_idx), batch_size=args.batch, shuffle=True,
                          num_workers=args.workers, pin_memory=True, drop_last=True, persistent_workers=args.workers > 0)
    val_dl = DataLoader(ManifestImages(val_rows, eval_tf, fake_idx, real_idx), batch_size=args.batch * 2,
                        num_workers=args.workers, pin_memory=True)

    if hasattr(model, "gradient_checkpointing_enable") and getattr(model, "supports_gradient_checkpointing", False):
        model.gradient_checkpointing_enable()
    freeze_lower_layers(model, args.freeze)
    model.to(device)

    optim = torch.optim.AdamW([p for p in model.parameters() if p.requires_grad], lr=args.lr, weight_decay=0.05)
    steps = math.ceil(len(train_dl) / args.accum) * args.epochs
    warmup = max(1, int(steps * 0.05))
    sched = torch.optim.lr_scheduler.LambdaLR(optim, lambda s: min(1.0, (s + 1) / warmup) * 0.5 * (1 + math.cos(math.pi * min(1.0, s / steps))))
    scaler = torch.amp.GradScaler("cuda", enabled=device == "cuda" and AMP)
    loss_fn = torch.nn.CrossEntropyLoss(label_smoothing=0.05)

    baseline = evaluate(model, val_dl, fake_idx, device)
    print(f"baseline (before training) val: {json.dumps(baseline)}")

    out = ROOT / args.out
    best, history = -1.0, []
    for epoch in range(1, args.epochs + 1):
        model.train()
        t0, running = time.time(), 0.0
        optim.zero_grad(set_to_none=True)
        for step, (x, target, _) in enumerate(train_dl, 1):
            with torch.autocast(device_type="cuda", dtype=torch.float16, enabled=device == "cuda" and AMP):
                loss = loss_fn(model(pixel_values=x.to(device, non_blocking=True)).logits.float(), target.to(device)) / args.accum
            scaler.scale(loss).backward()
            running += loss.item() * args.accum
            if step % args.accum == 0 or step == len(train_dl):
                scaler.unscale_(optim)
                torch.nn.utils.clip_grad_norm_(model.parameters(), 1.0)
                scaler.step(optim); scaler.update(); sched.step()
                optim.zero_grad(set_to_none=True)
            if step % 100 == 0:
                print(f"  epoch {epoch} step {step}/{len(train_dl)} loss={running / step:.4f} {(time.time() - t0) / step:.2f}s/step", flush=True)
        metrics = evaluate(model, val_dl, fake_idx, device)
        metrics.update(epoch=epoch, train_loss=running / len(train_dl), minutes=round((time.time() - t0) / 60, 1))
        history.append(metrics)
        print(f"epoch {epoch} val: {json.dumps(metrics)}", flush=True)
        if metrics["auc"] > best:
            best = metrics["auc"]
            out.mkdir(parents=True, exist_ok=True)
            model.save_pretrained(out, safe_serialization=True)
            processor.save_pretrained(out)
            print(f"  saved best -> {out}")

    meta = {
        "task": args.task, "base_model": args.base, "fake_label": labels[fake_idx],
        "trained_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "train_samples": len(train_rows), "val_samples": len(val_rows),
        "epochs": args.epochs, "lr": args.lr, "effective_batch": args.batch * args.accum,
        "baseline_val": baseline, "history": history, "best_val_auc": best,
        "data_sources": sorted({r["source"].split(":", 1)[0] for r in rows}),
    }
    (out / "training_meta.json").write_text(json.dumps(meta, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"done. best val AUC={best:.4f} (baseline {baseline['auc']:.4f})")


if __name__ == "__main__":
    main()
