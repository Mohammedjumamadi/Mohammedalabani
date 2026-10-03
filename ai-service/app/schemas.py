"""عقد الاستجابة بين خدمة الذكاء الاصطناعي ومنصة Laravel (موثق في docs/API.md)."""

from __future__ import annotations

from typing import Any, Literal

from pydantic import BaseModel, Field

MediaKind = Literal["image", "video", "audio"]
Severity = Literal["info", "low", "medium", "high"]


class ModelInfo(BaseModel):
    name: str
    version: str
    source: str | None = None
    license: str | None = None
    components: list[dict[str, Any]] = Field(default_factory=list)


class Indicator(BaseModel):
    code: str
    category: str
    severity: Severity = "info"
    score: float | None = Field(default=None, ge=0, le=100)
    description: str | None = None
    timestamp_start: float | None = None
    timestamp_end: float | None = None
    #: فهرس الإطار في قائمة frames (للإطارات المشبوهة)
    frame_index: int | None = None
    details: dict[str, Any] | None = None


class Frame(BaseModel):
    index: int
    timestamp: float
    suspicion: float = Field(ge=0, le=100)
    image_b64: str


class AnalysisResponse(BaseModel):
    media_type: MediaKind
    #: احتمال التلاعب/التزييف من 0 إلى 100، ناتج عن النموذج فقط
    manipulation_probability: float = Field(ge=0, le=100)
    authenticity_probability: float = Field(ge=0, le=100)
    #: ثقة النموذج في الفئة الراجحة (تقدير احتمالي وليس دليلًا)
    confidence: float = Field(ge=0, le=100)
    model: ModelInfo
    processing_time: float
    indicators: list[Indicator] = Field(default_factory=list)
    frames: list[Frame] = Field(default_factory=list)
    technical: dict[str, Any] = Field(default_factory=dict)


class ModelStatus(BaseModel):
    key: str
    media_type: MediaKind
    hf_id: str
    license: str | None
    loaded: bool
    error: str | None = None
    version: str | None = None


class HealthResponse(BaseModel):
    status: Literal["ok", "degraded"]
    service: str = "aimv-ai-service"
    version: str
    device: str
    ffmpeg: bool
    models: list[ModelStatus]
