"""إعدادات خدمة التحليل. تُقرأ من متغيرات البيئة (ويمكن وضعها في ملف .env بجوار المجلد app)."""

from __future__ import annotations

import json
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent


def _load_dotenv() -> None:
    """قارئ .env بسيط (KEY=VALUE) حتى لا نضيف اعتمادًا خارجيًا. لا يستبدل متغيرات موجودة أصلًا."""
    env_file = BASE_DIR / ".env"
    if not env_file.exists():
        return
    for line in env_file.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


_load_dotenv()


class Settings:
    #: المفتاح السري المشترك مع Laravel. بدونه ترفض الخدمة كل طلبات التحليل.
    api_key: str = os.environ.get("AI_SERVICE_KEY", "")
    #: الحد الأقصى لحجم الملف بالميغابايت (Laravel يفرض حدًّا أدنى منه أيضًا)
    max_upload_mb: int = int(os.environ.get("AI_MAX_UPLOAD_MB", "500"))
    #: cpu أو cuda
    device: str = os.environ.get("AI_DEVICE", "cpu")
    #: تحميل النماذج عند بدء التشغيل بدل أول طلب
    preload: bool = os.environ.get("AI_PRELOAD", "0") == "1"
    #: أقصى مدة صوت تُحلَّل (ثوانٍ)
    max_audio_seconds: int = int(os.environ.get("AI_MAX_AUDIO_SECONDS", "600"))
    #: أقصى مدة فيديو تُحلَّل (ثوانٍ)
    max_video_seconds: int = int(os.environ.get("AI_MAX_VIDEO_SECONDS", "900"))
    #: مسار ملف المعايرة الناتج عن سكربت التقييم (اختياري)
    calibration_path: Path = Path(os.environ.get("AI_CALIBRATION_PATH", str(BASE_DIR / "calibration.json")))


settings = Settings()

# أوزان الدمج والعتبات الافتراضية. تُستبدل قيمها بما يخرجه سكربت التقييم على بيانات التحقق (validation)،
# وهذه القيم الافتراضية اختيارات تصميمية معلنة وليست نتائج قياس.
DEFAULT_CALIBRATION: dict = {
    "image": {"w_face": 0.5, "w_gen": 0.5, "w_face_noface": 0.25, "w_gen_noface": 0.75},
    "video": {"w_video": 0.7, "w_audio": 0.3, "frame_threshold": 0.6, "max_frames": 24, "min_frames": 8},
    "audio": {"window_seconds": 5.0, "max_windows": 12, "window_threshold": 0.7},
}


def load_calibration() -> dict:
    calibration = json.loads(json.dumps(DEFAULT_CALIBRATION))
    path = settings.calibration_path
    if path.exists():
        try:
            override = json.loads(path.read_text(encoding="utf-8"))
            for section, values in override.items():
                if section in calibration and isinstance(values, dict):
                    calibration[section].update(values)
        except (OSError, ValueError):
            pass  # ملف تالف: نستمر بالقيم الافتراضية بدل إيقاف الخدمة
    return calibration
