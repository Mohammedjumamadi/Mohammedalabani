"""تعريف مجموعات بيانات التقييم العامة. كل مجموعة تُنزَّل منها عينة ثابتة (بذرة محددة) وليس المجموعة كلها.

لا تُستخدم أي من هذه البيانات لتدريب أي نموذج؛ النماذج مدرّبة مسبقًا من مصادر مفتوحة، وتُستخدم هذه العينات للتقييم فقط.
"""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class DatasetSpec:
    key: str
    media_type: str  # image | audio | video
    repo: str
    #: مجلد الفئة الحقيقية والمزيفة داخل المستودع
    real_dir: str
    fake_dir: str
    extensions: tuple[str, ...]
    license: str
    note: str


DATASETS: dict[str, DatasetSpec] = {
    # صور وجوه: حقيقية مقابل مزيفة
    "faces_real_vs_fake": DatasetSpec(
        key="faces_real_vs_fake",
        media_type="image",
        repo="Sowaiba01/Deepfake",
        real_dir="real",
        fake_dir="fake",
        extensions=(".jpg", ".jpeg", ".png"),
        license="mit",
        note="صور وجوه حقيقية ومزيفة (5426 لكل فئة). لا نعرف بيانات التدريب الدقيقة للنموذج، فقد تتقاطع معها.",
    ),
    # صور فنية: مولّدة بالذكاء الاصطناعي مقابل بشرية
    "ai_art_vs_real": DatasetSpec(
        key="ai_art_vs_real",
        media_type="image",
        repo="Hemg/AI-Generated-vs-Real-Images-Datasets",
        real_dir="RealArt/RealArt",
        fake_dir="AiArtData/AiArtData",
        extensions=(".jpg", ".jpeg", ".png", ".webp"),
        license="غير محدد في المستودع",
        note="أعمال فنية بشرية مقابل مولّدة (435 / 539). تقيس كشف الصور المولّدة لا تبديل الوجوه.",
    ),
    # أصوات: حقيقية مقابل مولّدة
    "voices_real_vs_tts": DatasetSpec(
        key="voices_real_vs_tts",
        media_type="audio",
        repo="garystafford/deepfake-audio-detection",
        real_dir="real",
        fake_dir="fake",
        extensions=(".flac", ".wav", ".mp3"),
        license="cc-by-4.0",
        note="مقاطع صوتية حقيقية ومولّدة بأدوات TTS/استنساخ (933 لكل فئة).",
    ),
    # فيديو: حقيقي مقابل تزييف عميق
    "videos_real_vs_deepfake": DatasetSpec(
        key="videos_real_vs_deepfake",
        media_type="video",
        repo="angads24/deepfake-video",
        real_dir="dataset/real",
        fake_dir="dataset/fake",
        extensions=(".mp4",),
        license="cc-by-sa-4.0 (نسخة معاد رفعها؛ الأصل يخضع لشروط FaceForensics++)",
        note="مقاطع فيديو حقيقية ومزيفة (200 لكل فئة). للاستخدام البحثي الأكاديمي فقط.",
    ),
}
