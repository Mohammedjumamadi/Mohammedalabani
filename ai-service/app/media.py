"""أدوات وسائط: اكتشاف FFmpeg، فك ترميز الصوت، وتحويل الإطارات إلى JPEG صغير."""

from __future__ import annotations

import base64
import glob
import json
import os
import shutil
import subprocess
from functools import lru_cache

import cv2
import numpy as np


@lru_cache(maxsize=1)
def find_binary(name: str) -> str | None:
    """يبحث عن ffmpeg/ffprobe في PATH ثم في مجلد حزم winget (قد لا يكون ضمن PATH لعملية قائمة)."""
    found = shutil.which(name)
    if found:
        return found
    local = os.environ.get("LOCALAPPDATA")
    if local:
        matches = glob.glob(os.path.join(local, "Microsoft", "WinGet", "Packages", "Gyan.FFmpeg*", "ffmpeg-*", "bin", f"{name}.exe"))
        if matches:
            return matches[0]
    return None


def ffmpeg_available() -> bool:
    return find_binary("ffmpeg") is not None


class MediaError(ValueError):
    """الملف تالف أو غير قابل للفك؛ تُترجم إلى HTTP 422."""


def probe(path: str) -> dict:
    """معلومات الوسائط عبر ffprobe (مدة، مسارات). تُرجع {} إن لم يتوفر ffprobe."""
    ffprobe = find_binary("ffprobe")
    if not ffprobe:
        return {}
    proc = subprocess.run(
        [ffprobe, "-v", "error", "-print_format", "json", "-show_format", "-show_streams", path],
        capture_output=True, text=True, timeout=60,
    )
    if proc.returncode != 0:
        raise MediaError("ffprobe could not read the file")
    return json.loads(proc.stdout or "{}")


def has_audio_stream(info: dict) -> bool:
    return any(s.get("codec_type") == "audio" for s in info.get("streams", []))


def decode_audio(path: str, sample_rate: int = 16000, max_seconds: int | None = None) -> np.ndarray:
    """يفك ترميز أي ملف صوت/فيديو إلى مصفوفة float32 أحادية القناة بمعدل عينة ثابت."""
    ffmpeg = find_binary("ffmpeg")
    if not ffmpeg:
        raise MediaError("ffmpeg is required for audio decoding")

    command = [ffmpeg, "-v", "error", "-i", path, "-vn", "-ac", "1", "-ar", str(sample_rate)]
    if max_seconds:
        command += ["-t", str(max_seconds)]
    command += ["-f", "f32le", "-"]

    proc = subprocess.run(command, capture_output=True, timeout=300)
    if proc.returncode != 0 or not proc.stdout:
        raise MediaError("Could not decode audio from the file")

    return np.frombuffer(proc.stdout, dtype=np.float32).copy()


def frame_to_jpeg_b64(rgb: np.ndarray, max_width: int = 480, quality: int = 80) -> str:
    """يحوّل إطار RGB إلى JPEG مصغّر بصيغة base64 لعرضه في تقرير النتيجة."""
    height, width = rgb.shape[:2]
    if width > max_width:
        scale = max_width / width
        rgb = cv2.resize(rgb, (max_width, int(height * scale)), interpolation=cv2.INTER_AREA)
    ok, buffer = cv2.imencode(".jpg", cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR), [cv2.IMWRITE_JPEG_QUALITY, quality])
    if not ok:
        raise MediaError("Could not encode frame")
    return base64.b64encode(buffer.tobytes()).decode("ascii")
