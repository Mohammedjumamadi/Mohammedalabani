import io
import os

os.environ.setdefault("AI_SERVICE_KEY", "test-secret-key")

import numpy as np
import pytest
from fastapi.testclient import TestClient
from PIL import Image


@pytest.fixture()
def client():
    from app.main import app

    return TestClient(app)


@pytest.fixture()
def api_key():
    return os.environ["AI_SERVICE_KEY"]


def make_jpeg_bytes(size=(64, 64), color=(120, 60, 200)) -> bytes:
    img = Image.new("RGB", size, color)
    buf = io.BytesIO()
    img.save(buf, "JPEG", quality=85)
    return buf.getvalue()


def make_wav_bytes(seconds=2.0, sr=16000) -> bytes:
    import wave

    t = np.linspace(0, seconds, int(sr * seconds), endpoint=False)
    signal = (0.2 * np.sin(2 * np.pi * 220 * t) * 32767).astype(np.int16)
    buf = io.BytesIO()
    with wave.open(buf, "wb") as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sr)
        f.writeframes(signal.tobytes())
    return buf.getvalue()
