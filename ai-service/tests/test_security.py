"""مصادقة المفتاح السري: يجب أن تُرفض كل الطلبات بلا مفتاح صحيح، ولا تُسرَّب تفاصيل داخلية."""

from tests.conftest import make_jpeg_bytes


def test_health_requires_no_key(client):
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["service"] == "aimv-ai-service"


def test_models_endpoint_requires_a_valid_key(client, api_key):
    assert client.get("/models").status_code == 401
    assert client.get("/models", headers={"X-API-Key": "wrong"}).status_code == 401

    response = client.get("/models", headers={"X-API-Key": api_key})
    assert response.status_code == 200
    assert {m["key"] for m in response.json()} == {"image_face", "image_gen", "audio_voice"}


def test_analyze_requires_a_valid_key(client):
    files = {"file": ("a.jpg", make_jpeg_bytes(), "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files)
    assert response.status_code == 401


def test_missing_configured_key_rejects_everything(monkeypatch, client):
    from app import config

    monkeypatch.setattr(config.settings, "api_key", "")
    response = client.get("/models", headers={"X-API-Key": "anything"})
    assert response.status_code == 503
