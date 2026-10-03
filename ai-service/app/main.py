"""خدمة الذكاء الاصطناعي لمنصة AI Media Verifier (FastAPI).

نقاط النهاية:
  GET  /health       حالة الخدمة والنماذج (بلا مصادقة، بلا بيانات حساسة)
  GET  /models       سجل النماذج المستخدمة ومصادرها وتراخيصها (يتطلب المفتاح)
  POST /v1/analyze   تحليل ملف (image|video|audio) وإرجاع احتمال التلاعب والمؤشرات (يتطلب المفتاح)
"""

from __future__ import annotations

import logging
import os
import shutil
import tempfile
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Annotated

from fastapi import Depends, FastAPI, File, Form, HTTPException, UploadFile, status
from fastapi.concurrency import run_in_threadpool

from . import __version__
from .analyzers.audio import analyze_audio
from .analyzers.image import analyze_image
from .analyzers.video import analyze_video
from .config import settings
from .media import MediaError, ffmpeg_available
from .registry import SPECS, ModelUnavailable, registry
from .schemas import AnalysisResponse, HealthResponse, MediaKind, ModelStatus
from .security import require_api_key

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger("aimv.api")

ANALYZERS = {"image": analyze_image, "video": analyze_video, "audio": analyze_audio}
EXTENSIONS = {
    "image": {".jpg", ".jpeg", ".png", ".webp"},
    "video": {".mp4", ".mov", ".avi", ".webm", ".mkv"},
    "audio": {".mp3", ".wav", ".m4a", ".ogg", ".flac"},
}


@asynccontextmanager
async def lifespan(_: FastAPI):
    if settings.preload:
        await run_in_threadpool(registry.preload)
    yield


app = FastAPI(
    title="AI Media Verifier — AI Service",
    version=__version__,
    description="خدمة تحليل الصور والفيديو والصوت للكشف عن التلاعب. تعيد تقديرات احتمالية وليست أدلة قطعية.",
    lifespan=lifespan,
)


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    rows = registry.status()
    ffmpeg = ffmpeg_available()
    return HealthResponse(
        status="ok" if ffmpeg and all(not r["error"] for r in rows) else "degraded",
        version=__version__,
        device=settings.device,
        ffmpeg=ffmpeg,
        models=[ModelStatus(**r) for r in rows],
    )


@app.get("/models", dependencies=[Depends(require_api_key)])
def models() -> list[dict]:
    """معلومات كل نموذج: الاسم والمصدر والترخيص والمهمة (لتوثيقها في لوحة المدير)."""
    status_by_key = {r["key"]: r for r in registry.status()}
    return [
        {
            "key": spec.key, "name": spec.hf_id, "media_type": spec.media_type, "task": spec.task,
            "license": spec.license, "source": spec.source_url, "description": spec.description,
            "loaded": status_by_key[spec.key]["loaded"], "version": status_by_key[spec.key]["version"],
        }
        for spec in SPECS.values()
    ]


@app.post("/v1/analyze", response_model=AnalysisResponse, dependencies=[Depends(require_api_key)])
async def analyze(
    file: Annotated[UploadFile, File()],
    media_type: Annotated[MediaKind, Form()],
) -> dict:
    suffix = Path(file.filename or "").suffix.lower()
    if suffix not in EXTENSIONS[media_type]:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "Unsupported file extension for this media type")

    max_bytes = settings.max_upload_mb * 1024 * 1024
    tmp_dir = tempfile.mkdtemp(prefix="aimv_")
    path = os.path.join(tmp_dir, f"upload{suffix}")

    try:
        written = 0
        with open(path, "wb") as out:
            while chunk := await file.read(1024 * 1024):
                written += len(chunk)
                if written > max_bytes:
                    raise HTTPException(status.HTTP_413_REQUEST_ENTITY_TOO_LARGE, "File too large")
                out.write(chunk)
        if written == 0:
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "Empty file")

        try:
            return await run_in_threadpool(ANALYZERS[media_type], path)
        except ModelUnavailable as exc:
            log.error("Model unavailable: %s", exc)
            raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "No analysis model is available for this media type") from exc
        except MediaError as exc:
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, str(exc)) from exc
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)
