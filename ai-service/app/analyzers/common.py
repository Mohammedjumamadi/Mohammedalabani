"""أدوات مشتركة بين محللات الصور والصوت والفيديو."""

from __future__ import annotations

from typing import Any

import numpy as np

from ..registry import LoadedModel


def severity(score: float) -> str:
    """تحويل درجة (0..100) إلى مستوى: قوي ≥70، متوسط ≥40، ضعيف ≥20، وإلا معلومة."""
    if score >= 70:
        return "high"
    if score >= 40:
        return "medium"
    if score >= 20:
        return "low"
    return "info"


def indicator(code: str, category: str, *, score: float | None = None, sev: str | None = None,
              description: str | None = None, start: float | None = None, end: float | None = None,
              frame_index: int | None = None, experimental: bool = False, details: dict[str, Any] | None = None) -> dict:
    payload_details = dict(details or {})
    if experimental:
        # قياسات لم يُتحقق من دقتها على بيانات اختبار: تُعرض للمستخدم بوسم «تجريبي»
        payload_details["experimental"] = True

    return {
        "code": code,
        "category": category,
        "severity": sev or (severity(score) if score is not None else "info"),
        "score": None if score is None else round(float(np.clip(score, 0, 100)), 2),
        "description": description,
        "timestamp_start": None if start is None else round(float(start), 3),
        "timestamp_end": None if end is None else round(float(end), 3),
        "frame_index": frame_index,
        "details": payload_details or None,
    }


def ensemble_model_info(name: str, version: str, models: list[LoadedModel]) -> dict:
    """وصف النموذج المركّب: اسم موحّد للمنصة، مع تفصيل كل نموذج مفتوح المصدر مستخدم ومصدره وترخيصه."""
    return {
        "name": name,
        "version": f"{version} [" + "+".join(m.version for m in models) + "]",
        "source": ", ".join(m.spec.source_url for m in models),
        "license": ", ".join(sorted({m.spec.license for m in models})),
        "components": [
            {"key": m.spec.key, "hf_id": m.spec.hf_id, "revision": m.version, "license": m.spec.license, "task": m.spec.task}
            for m in models
        ],
    }
