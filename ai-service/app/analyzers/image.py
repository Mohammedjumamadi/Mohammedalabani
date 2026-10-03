"""محلل الصور: نموذجان مفتوحا المصدر (وجوه مزيفة + صور مولّدة) مع فحوص جنائية مساعدة."""

from __future__ import annotations

import time

import numpy as np
from PIL import Image, ImageOps

from .. import forensics
from ..config import load_calibration
from ..faces import crop_with_margin, detect_faces
from ..media import MediaError
from ..registry import LoadedModel, ModelUnavailable, registry
from .common import ensemble_model_info, indicator, severity

MODEL_NAME = "aimv-image-ensemble"
MODEL_VERSION = "1.0.0"

#: إن ظهر في البيانات الوصفية اسم أداة توليد صور معروفة، فهذا دليل مباشر لا استنتاج إحصائي
METADATA_AI_TOOL_FLOOR = 90.0


def _score(lm: LoadedModel, image: Image.Image) -> float:
    inputs = lm.processor(images=image.convert("RGB"), return_tensors="pt")
    return lm.fake_probability(dict(inputs))


def _safe_get(key: str) -> LoadedModel | None:
    try:
        return registry.get(key)
    except ModelUnavailable:
        return None


def analyze_image(path: str) -> dict:
    started = time.perf_counter()
    cal = load_calibration()["image"]

    try:
        with Image.open(path) as opened:
            opened.load()
            original = opened.copy()
            original.info = dict(opened.info)
            fmt = opened.format
    except Exception as exc:  # noqa: BLE001
        raise MediaError("Cannot read the image") from exc

    img = ImageOps.exif_transpose(original).convert("RGB")
    rgb = np.asarray(img)
    faces = detect_faces(rgb)

    face_model, gen_model = _safe_get("image_face"), _safe_get("image_gen")
    if face_model is None and gen_model is None:
        raise ModelUnavailable("No image model is available")

    p_face = p_gen = None
    face_source = "face_crop" if faces else "whole_image"
    if face_model is not None:
        target = Image.fromarray(crop_with_margin(rgb, faces[0])) if faces else img
        p_face = _score(face_model, target)
    if gen_model is not None:
        p_gen = _score(gen_model, img)

    weights = (cal["w_face"], cal["w_gen"]) if faces else (cal["w_face_noface"], cal["w_gen_noface"])
    parts = [(p, w) for p, w in ((p_face, weights[0]), (p_gen, weights[1])) if p is not None]
    probability = sum(p * w for p, w in parts) / sum(w for _, w in parts) * 100

    indicators: list[dict] = []
    if p_face is not None:
        indicators.append(indicator(
            "model_deepfake_score", "model", score=p_face * 100,
            details={"analyzed": face_source, "model": face_model.spec.hf_id},
        ))
    if p_gen is not None:
        indicators.append(indicator(
            "model_ai_generated_score", "model", score=p_gen * 100, details={"model": gen_model.spec.hf_id},
        ))
    if not faces:
        indicators.append(indicator("no_face_detected", "visual", sev="info"))

    # ---- فحوص مساعدة (لا تدخل في الاحتمال النهائي إلا الدليل الصريح لأداة توليد في البيانات الوصفية)
    meta = forensics.metadata_findings(original)
    if meta["ai_tool"]:
        indicators.append(indicator(
            "metadata_ai_tool", "metadata", score=100, sev="high",
            description=f"ai_tool={meta['ai_tool']}", details={"tool": meta["ai_tool"]},
        ))
        probability = max(probability, METADATA_AI_TOOL_FLOOR)
    if meta["editing_software"]:
        indicators.append(indicator(
            "metadata_editing_software", "metadata", sev="medium", description=str(meta["software"]),
            details={"software": meta["software"]},
        ))
    if not meta["has_camera_data"] and not meta["ai_tool"]:
        indicators.append(indicator("metadata_missing", "metadata", sev="low", details={"exif_present": meta["exif_present"]}))

    ela = forensics.error_level_analysis(original)
    indicators.append(indicator("ela_anomaly", "compression", score=ela["score"], experimental=True, details=ela))

    quality = forensics.jpeg_quality_estimate(original) if fmt == "JPEG" else None
    if quality is not None:
        indicators.append(indicator(
            "compression_traces", "compression", sev="low" if quality < 70 else "info", experimental=True,
            details={"estimated_jpeg_quality": round(quality, 1)},
        ))

    if faces:
        ratio = forensics.face_edge_sharpness_ratio(rgb, faces[0])
        if ratio is not None:
            deviation = abs(float(np.log2(ratio)))
            indicators.append(indicator(
                "face_edge_artifacts", "visual", score=min(100.0, deviation / 3 * 100), experimental=True,
                details={"sharpness_ratio": round(ratio, 3)},
            ))
        smooth = forensics.skin_smoothness(rgb, faces[0])
        if smooth is not None:
            score = float(np.clip((15 - smooth) / 15 * 100, 0, 100))
            indicators.append(indicator("skin_texture_smooth", "visual", score=score, experimental=True, details={"laplacian_variance": round(smooth, 2)}))

    used = [m for m in (face_model, gen_model) if m is not None]
    return {
        "media_type": "image",
        "manipulation_probability": round(float(probability), 2),
        "authenticity_probability": round(100 - float(probability), 2),
        "confidence": round(max(probability, 100 - probability), 2),
        "model": ensemble_model_info(MODEL_NAME, MODEL_VERSION, used),
        "processing_time": round(time.perf_counter() - started, 2),
        "indicators": indicators,
        "frames": [],
        "technical": {
            "width": img.width, "height": img.height, "format": fmt, "faces_detected": len(faces),
            "components_used": [m.spec.key for m in used],
            "weights": {"face": weights[0], "gen": weights[1]},
            "metadata_override": bool(meta["ai_tool"]),
            "severity_of_result": severity(probability),
        },
    }
