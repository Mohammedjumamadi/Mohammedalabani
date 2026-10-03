"""فحوص جنائية الصور: ELA والبيانات الوصفية. اختبارات على صور اصطناعية بسيطة، لا على بيانات حقيقية."""

import io

from PIL import Image

from app import forensics


def test_ela_score_is_higher_for_a_locally_edited_region():
    base = Image.new("RGB", (256, 256), (120, 120, 120))
    buf = io.BytesIO()
    base.save(buf, "JPEG", quality=95)
    buf.seek(0)
    clean = Image.open(buf)

    edited = clean.copy()
    for x in range(160, 220):
        for y in range(160, 220):
            edited.putpixel((x, y), (255, 0, 0))  # منطقة مربعة معدّلة بشدة بعد إعادة الضغط

    clean_score = forensics.error_level_analysis(clean)["score"]
    edited_score = forensics.error_level_analysis(edited)["score"]

    assert edited_score >= clean_score


def test_metadata_detects_known_ai_tool_strings():
    img = Image.new("RGB", (32, 32))
    img.info["parameters"] = "Steps: 20, Sampler: Euler, Model: stable diffusion xl"

    findings = forensics.metadata_findings(img)

    assert findings["ai_tool"] == "stable diffusion"


def test_metadata_missing_camera_data_when_no_exif():
    img = Image.new("RGB", (32, 32))

    findings = forensics.metadata_findings(img)

    assert findings["has_camera_data"] is False
    assert findings["ai_tool"] is None
    assert findings["editing_software"] is None


def test_jpeg_quality_estimate_is_none_for_non_jpeg():
    img = Image.new("RGB", (16, 16))
    assert forensics.jpeg_quality_estimate(img) is None
