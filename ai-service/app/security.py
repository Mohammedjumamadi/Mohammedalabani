"""مصادقة الطلبات القادمة من Laravel بمفتاح سري مشترك في ترويسة X-API-Key."""

from __future__ import annotations

import hmac

from fastapi import Header, HTTPException, status

from .config import settings


def require_api_key(x_api_key: str | None = Header(default=None)) -> None:
    if not settings.api_key:
        # لا نعمل بلا مفتاح حتى لا تُترك الخدمة مفتوحة بالخطأ
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "AI_SERVICE_KEY is not configured")

    # مقارنة ثابتة الزمن تمنع تخمين المفتاح من فروق زمن الاستجابة
    if not x_api_key or not hmac.compare_digest(x_api_key.encode(), settings.api_key.encode()):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid API key")
