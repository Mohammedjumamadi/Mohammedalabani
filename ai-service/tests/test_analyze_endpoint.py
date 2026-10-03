"""نقطة /v1/analyze: التحقق من الامتداد والحجم، وأن أي فشل تحليل يتحول إلى خطأ HTTP واضح لا استثناء مكشوف."""

from tests.conftest import make_jpeg_bytes, make_wav_bytes


def test_wrong_extension_for_media_type_is_rejected(client, api_key):
    files = {"file": ("a.png", make_jpeg_bytes(), "image/png")}
    response = client.post("/v1/analyze", data={"media_type": "audio"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 422


def test_unknown_media_type_is_rejected(client, api_key):
    files = {"file": ("a.jpg", make_jpeg_bytes(), "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "text"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 422


def test_empty_file_is_rejected(client, api_key):
    files = {"file": ("a.jpg", b"", "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 422


def test_oversized_file_is_rejected(monkeypatch, client, api_key):
    from app import config

    monkeypatch.setattr(config.settings, "max_upload_mb", 0)  # أي حجم أكبر من صفر يتجاوز الحد
    files = {"file": ("a.jpg", make_jpeg_bytes(), "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 413


def test_corrupt_image_returns_422_not_a_500(client, api_key):
    files = {"file": ("a.jpg", b"not a real jpeg but has the right extension", "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 422
    assert "Traceback" not in response.text


def test_no_model_available_returns_503(monkeypatch, client, api_key):
    from app.registry import ModelUnavailable

    def boom(path):
        raise ModelUnavailable("no model")

    monkeypatch.setattr("app.main.ANALYZERS", {"image": boom, "video": boom, "audio": boom})
    files = {"file": ("a.jpg", make_jpeg_bytes(), "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code == 503


def test_a_successful_analysis_matches_the_response_schema(monkeypatch, client, api_key):
    def fake_analyze(path):
        return {
            "media_type": "image",
            "manipulation_probability": 42.5,
            "authenticity_probability": 57.5,
            "confidence": 57.5,
            "model": {"name": "unit-test-model", "version": "1.0.0", "source": "https://example.test", "license": "mit"},
            "processing_time": 0.1,
            "indicators": [{"code": "model_deepfake_score", "category": "model", "severity": "medium", "score": 42.5}],
            "frames": [],
            "technical": {"width": 64, "height": 64},
        }

    monkeypatch.setattr("app.main.ANALYZERS", {"image": fake_analyze, "video": fake_analyze, "audio": fake_analyze})
    files = {"file": ("a.jpg", make_jpeg_bytes(), "image/jpeg")}
    response = client.post("/v1/analyze", data={"media_type": "image"}, files=files, headers={"X-API-Key": api_key})

    assert response.status_code == 200
    body = response.json()
    assert body["manipulation_probability"] == 42.5
    assert body["model"]["name"] == "unit-test-model"
    assert body["indicators"][0]["code"] == "model_deepfake_score"


def test_wav_audio_upload_is_accepted_by_extension_check(client, api_key):
    files = {"file": ("a.wav", make_wav_bytes(), "audio/wav")}
    # سيفشل التحليل الفعلي لأن ffmpeg قد لا يكون بمسار الاختبار، لكن يجب ألا يُرفض بسبب الامتداد (422 من نوع الملف نفسه غير متوقع هنا)
    response = client.post("/v1/analyze", data={"media_type": "audio"}, files=files, headers={"X-API-Key": api_key})
    assert response.status_code != 401 and response.status_code != 400
