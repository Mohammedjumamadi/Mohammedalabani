"""فحوص جنائية رقمية مساعدة للصور (ELA، البيانات الوصفية، الضغط، حواف الوجه).

هذه مؤشرات مساعدة تُعرض للمستخدم مع شرحها، ولا تدخل في احتمال التلاعب النهائي الذي يصدره النموذج.
تُوسَم قياساتها غير المُتحقَّق منها بعلامة experimental حتى لا تُقرأ كأدلة قاطعة.
"""

from __future__ import annotations

import io
from typing import Any

import cv2
import numpy as np
from PIL import ExifTags, Image

from .faces import Box, crop_with_margin

#: كلمات في وسم Software أو نصوص PNG تدل على برنامج تحرير أو مولّد صور
EDITING_SOFTWARE = ("photoshop", "lightroom", "gimp", "canva", "snapseed", "pixlr", "facetune", "affinity", "paint.net", "capture one")
AI_TOOLS = ("stable diffusion", "midjourney", "dall-e", "dalle", "firefly", "comfyui", "automatic1111", "novelai", "leonardo", "sdxl", "invokeai", "flux")


def error_level_analysis(img: Image.Image, quality: int = 90) -> dict[str, float]:
    """ELA: نعيد ضغط الصورة JPEG بجودة معروفة ونقيس الفرق. تفاوت الفرق بين المناطق قد يدل على تحرير موضعي.

    نعيد درجة تباين الخطأ بين كتل الصورة (0..100). القيمة العالية = تفاوت أكبر في مستوى الضغط بين المناطق.
    """
    rgb = img.convert("RGB")
    if max(rgb.size) > 1024:
        rgb.thumbnail((1024, 1024))
    buffer = io.BytesIO()
    rgb.save(buffer, "JPEG", quality=quality)
    buffer.seek(0)
    resaved = Image.open(buffer).convert("RGB")

    diff = np.abs(np.asarray(rgb, dtype=np.int16) - np.asarray(resaved, dtype=np.int16)).astype(np.float32).mean(axis=2)
    height, width = diff.shape
    block = 32
    blocks = [
        diff[y:y + block, x:x + block].mean()
        for y in range(0, height - block + 1, block)
        for x in range(0, width - block + 1, block)
    ]
    if len(blocks) < 4:
        return {"mean_error": float(diff.mean()), "score": 0.0}

    blocks_arr = np.asarray(blocks)
    mean = float(blocks_arr.mean())
    cv = float(blocks_arr.std() / mean) if mean > 1e-6 else 0.0
    # cv ≈ 0.5 يعني تفاوتًا طبيعيًا في الصور العادية، وقيم أعلى بكثير تعني مناطق مختلفة الضغط
    score = float(np.clip((cv - 0.4) / 1.6 * 100, 0, 100))
    return {"mean_error": mean, "block_cv": cv, "score": score}


def jpeg_quality_estimate(img: Image.Image) -> float | None:
    """تقدير جودة JPEG من جداول التكميم المخزنة في الملف (None لغير JPEG)."""
    tables = getattr(img, "quantization", None)
    if not tables or 0 not in tables:
        return None
    table = np.asarray(tables[0], dtype=np.float64)
    # جدول لومينانس القياسي عند الجودة 50 مجموعه ≈ 5400؛ نقدّر الجودة من نسبة المجموع
    ratio = table.sum() / 5400.0
    quality = 50.0 / ratio if ratio > 1 else 100.0 - 50.0 * ratio
    return float(np.clip(quality, 1, 100))


def metadata_findings(img: Image.Image) -> dict[str, Any]:
    """يستخرج ما يهم من البيانات الوصفية دون أي إحداثيات GPS."""
    exif = {}
    try:
        for tag_id, value in img.getexif().items():
            name = ExifTags.TAGS.get(tag_id, str(tag_id))
            if isinstance(value, (str, int, float)):
                exif[name] = value
    except Exception:  # noqa: BLE001 - EXIF تالف لا يمنع التحليل
        exif = {}

    text_chunks = {str(k).lower(): str(v)[:400] for k, v in (img.info or {}).items() if isinstance(v, (str, bytes)) and str(k).lower() in ("parameters", "software", "comment", "description", "prompt", "workflow")}

    software = str(exif.get("Software", "")).lower()
    combined = " ".join([software, *[v.lower() for v in text_chunks.values()]])

    return {
        "has_camera_data": bool(exif.get("Make") or exif.get("Model")),
        "software": exif.get("Software"),
        "editing_software": next((s for s in EDITING_SOFTWARE if s in software), None),
        "ai_tool": next((t for t in AI_TOOLS if t in combined), None),
        "exif_present": bool(exif),
    }


def face_edge_sharpness_ratio(rgb: np.ndarray, box: Box) -> float | None:
    """نسبة حدة الوجه إلى حدة ما حوله (تباين لابلاسيان). نسبة بعيدة عن 1 قد تدل على لصق وجه في صورة أخرى."""
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
    face = gray[box.y:box.y + box.h, box.x:box.x + box.w]
    if face.size < 900:
        return None
    ring = crop_with_margin(gray, box, margin=0.6)
    if ring.size <= face.size * 1.2:
        return None

    face_var = cv2.Laplacian(face, cv2.CV_64F).var()
    ring_var = (cv2.Laplacian(ring, cv2.CV_64F).var() * ring.size - face_var * face.size) / max(1, ring.size - face.size)
    if ring_var <= 1e-6 or face_var <= 1e-6:
        return None
    return float(face_var / ring_var)


def skin_smoothness(rgb: np.ndarray, box: Box) -> float | None:
    """تباين لابلاسيان في وسط الوجه (الخد والجبهة). القيم المنخفضة جدًا تعني بشرة ناعمة بصورة غير معتادة."""
    x1, y1 = box.x + int(box.w * 0.25), box.y + int(box.h * 0.3)
    x2, y2 = box.x + int(box.w * 0.75), box.y + int(box.h * 0.75)
    patch = cv2.cvtColor(rgb[y1:y2, x1:x2], cv2.COLOR_RGB2GRAY)
    if patch.size < 400:
        return None
    # توحيد الحجم يجعل القياس مستقلًا عن دقة الصورة
    patch = cv2.resize(patch, (96, 96), interpolation=cv2.INTER_AREA)
    return float(cv2.Laplacian(patch, cv2.CV_64F).var())
