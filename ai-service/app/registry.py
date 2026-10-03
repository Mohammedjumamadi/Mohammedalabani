"""سجل النماذج: نماذج مفتوحة المصدر ومدرّبة مسبقًا من Hugging Face، تُحمَّل عند الحاجة.

لا نُدرّب أي نموذج من الصفر. لكل نموذج: الاسم والمصدر والترخيص والمهمة، وأسماء التسميات التي تعني «مزيف».
إذا تعذّر التأكد من تسميات النموذج نرفض استخدامه بدل تخمين اتجاه النتيجة.
"""

from __future__ import annotations

import logging
import os
import threading
from dataclasses import dataclass, field
from typing import Any

import numpy as np

from .config import BASE_DIR, settings

log = logging.getLogger("aimv.registry")


class ModelUnavailable(RuntimeError):
    """النموذج غير محمّل أو تعذّر تحميله؛ تُترجم إلى HTTP 503 (no_model)."""


@dataclass(frozen=True)
class ModelSpec:
    key: str
    hf_id: str
    media_type: str  # image | audio
    task: str  # image-classification | audio-classification
    license: str
    description: str
    #: أسماء التسميات (بأحرف صغيرة) التي تعني «مزيف/مولّد»
    fake_labels: frozenset[str]

    @property
    def source_url(self) -> str:
        return f"https://huggingface.co/{self.hf_id}"


SPECS: dict[str, ModelSpec] = {
    "image_face": ModelSpec(
        key="image_face",
        hf_id="prithivMLmods/Deep-Fake-Detector-v2-Model",
        media_type="image",
        task="image-classification",
        license="apache-2.0",
        description="ViT مضبوط لتمييز الوجوه الحقيقية من المزيفة (Deepfake vs Realism)",
        fake_labels=frozenset({"deepfake", "fake"}),
    ),
    "image_gen": ModelSpec(
        key="image_gen",
        hf_id="umm-maybe/AI-image-detector",
        media_type="image",
        task="image-classification",
        license="cc-by-4.0",
        description="نموذج لتمييز الصور المولّدة بالذكاء الاصطناعي من الصور البشرية",
        fake_labels=frozenset({"artificial", "ai", "fake"}),
    ),
    "audio_voice": ModelSpec(
        key="audio_voice",
        # استُبدل MelodyMachine/Deepfake-audio-detection-V2 (AUC≈0.49 على عينة تقييم مستقلة، أي بمستوى التخمين)
        # بهذا النموذج بعد مقارنة تجريبية (evaluation/bench_candidate.py) أظهرت AUC≈0.86 على العينة نفسها.
        hf_id="mo-thecreator/Deepfake-audio-detection",
        media_type="audio",
        task="audio-classification",
        license="apache-2.0",
        description="wav2vec2 مضبوط لتمييز الأصوات الحقيقية من المولّدة/المستنسخة",
        fake_labels=frozenset({"fake", "spoof", "deepfake", "artificial"}),
    ),
}


#: مجلد النماذج المضبوطة محليًا: ai-service/models/<key>/ (يحتوي config.json وmodel.safetensors وtraining_meta.json)
TRAINED_DIR = BASE_DIR / "models"


def trained_model_dir(key: str):
    """مسار النسخة المضبوطة محليًا إن وُجدت وكان استخدامها مفعّلًا (AI_USE_TRAINED=1 افتراضيًا)."""
    if os.environ.get("AI_USE_TRAINED", "1") != "1":
        return None
    path = TRAINED_DIR / key
    if (path / "config.json").exists() and (path / "model.safetensors").exists():
        return path
    return None


def trained_meta(key: str) -> dict | None:
    import json

    path = TRAINED_DIR / key / "training_meta.json"
    try:
        return json.loads(path.read_text(encoding="utf-8")) if path.exists() else None
    except (OSError, ValueError):
        return None


@dataclass
class LoadedModel:
    spec: ModelSpec
    processor: Any
    model: Any
    fake_index: int
    labels: dict[int, str]
    version: str

    def fake_probability(self, inputs: dict) -> float:
        """احتمال «مزيف» (0..1) لمدخل واحد عبر softmax على مخرجات النموذج."""
        import torch

        with torch.no_grad():
            logits = self.model(**{k: v.to(settings.device) for k, v in inputs.items()}).logits
            probs = torch.softmax(logits, dim=-1)[0].cpu().numpy()
        return float(probs[self.fake_index])

    def fake_probabilities_batch(self, inputs: dict) -> np.ndarray:
        import torch

        with torch.no_grad():
            logits = self.model(**{k: v.to(settings.device) for k, v in inputs.items()}).logits
            probs = torch.softmax(logits, dim=-1).cpu().numpy()
        return probs[:, self.fake_index]


class Registry:
    def __init__(self) -> None:
        self._loaded: dict[str, LoadedModel] = {}
        self._errors: dict[str, str] = {}
        self._lock = threading.Lock()

    # ------------------------------------------------------------------ تحميل
    def get(self, key: str) -> LoadedModel:
        if key in self._loaded:
            return self._loaded[key]

        with self._lock:
            if key in self._loaded:
                return self._loaded[key]
            try:
                self._loaded[key] = self._load(SPECS[key])
                self._errors.pop(key, None)
            except Exception as exc:  # noqa: BLE001 - نحوّل أي فشل تحميل إلى ModelUnavailable
                message = f"{type(exc).__name__}: {exc}"
                self._errors[key] = message[:300]
                log.error("Failed to load model %s: %s", key, message)
                raise ModelUnavailable(f"Model '{key}' is unavailable") from exc
        return self._loaded[key]

    def _load(self, spec: ModelSpec) -> LoadedModel:
        import torch
        from huggingface_hub import snapshot_download
        from transformers import (
            AutoFeatureExtractor,
            AutoImageProcessor,
            AutoModelForAudioClassification,
            AutoModelForImageClassification,
        )

        trained = trained_model_dir(spec.key)
        if trained is not None:
            # نسخة مضبوطة محليًا (training/) لم تُنسخ إلى هنا إلا بعد تفوّقها على النموذج الأصلي في التقييم
            path = str(trained)
            meta = trained_meta(spec.key) or {}
            version = "ft-" + str(meta.get("trained_at", "local"))[:10]
            log.info("Loading fine-tuned model %s from %s (base %s)", spec.key, path, spec.hf_id)
        else:
            log.info("Loading model %s (%s)", spec.key, spec.hf_id)
            # نفضّل صيغة safetensors (لا تنفّذ شيفرة عند التحميل)، ونرجع إلى pytorch_model.bin فقط إن لم تتوفر
            path = snapshot_download(spec.hf_id, allow_patterns=["*.json", "*.safetensors", "*.txt"])
            has_weights = any(os.path.exists(os.path.join(path, n)) for n in ("model.safetensors", "model.safetensors.index.json"))
            if not has_weights:
                path = snapshot_download(spec.hf_id, allow_patterns=["*.json", "*.txt", "pytorch_model.bin"])
            version = path.replace("\\", "/").rstrip("/").split("/")[-1][:10]

        if spec.task == "image-classification":
            processor = AutoImageProcessor.from_pretrained(path)
            model = AutoModelForImageClassification.from_pretrained(path)
        else:
            processor = AutoFeatureExtractor.from_pretrained(path)
            model = AutoModelForAudioClassification.from_pretrained(path)

        model.to(settings.device).eval()
        torch.set_grad_enabled(False)

        labels = {int(i): str(name) for i, name in model.config.id2label.items()}
        fake_indices = [i for i, name in labels.items() if name.lower() in spec.fake_labels]
        if len(fake_indices) != 1:
            # لا نخمّن اتجاه النتيجة: تسميات غير متوقعة تعني أن النموذج غير صالح لهذا الاستخدام
            raise ModelUnavailable(f"Cannot identify the 'fake' label among {labels}")

        return LoadedModel(spec=spec, processor=processor, model=model, fake_index=fake_indices[0], labels=labels, version=version)

    # ------------------------------------------------------------------ حالة
    def status(self) -> list[dict]:
        rows = []
        for key, spec in SPECS.items():
            loaded = self._loaded.get(key)
            rows.append({
                "key": key,
                "media_type": spec.media_type,
                "hf_id": spec.hf_id,
                "license": spec.license,
                "loaded": loaded is not None,
                "error": self._errors.get(key),
                "version": loaded.version if loaded else None,
                # هل تعمل نسخة مضبوطة محليًا على بيانات إضافية؟ (المصدر الأصلي يبقى hf_id للإسناد والترخيص)
                "fine_tuned": trained_model_dir(key) is not None,
            })
        return rows

    def preload(self) -> None:
        for key in SPECS:
            try:
                self.get(key)
            except ModelUnavailable:
                pass


registry = Registry()
