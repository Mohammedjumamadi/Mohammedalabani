"""دالة indicator() المشتركة: تحويل الدرجة إلى شدة، تقييد النطاق، ووسم القياسات التجريبية."""

from app.analyzers.common import indicator, severity


def test_severity_thresholds():
    assert severity(15) == "info"
    assert severity(25) == "low"
    assert severity(55) == "medium"
    assert severity(85) == "high"


def test_indicator_clamps_score_to_0_100():
    assert indicator("x", "model", score=150)["score"] == 100.0
    assert indicator("x", "model", score=-10)["score"] == 0.0


def test_experimental_flag_is_recorded_in_details():
    result = indicator("x", "audio", score=10, experimental=True)
    assert result["details"]["experimental"] is True


def test_non_experimental_indicator_has_no_experimental_flag():
    result = indicator("x", "model", score=10)
    assert result["details"] is None
