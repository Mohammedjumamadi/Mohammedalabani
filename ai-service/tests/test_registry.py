"""سجل النماذج: لا يخمّن اتجاه «مزيف» عندما تكون التسميات غامضة، ويُسجّل الأخطاء بدل أن يسقط الخدمة."""

import pytest

from app.registry import ModelSpec, ModelUnavailable, Registry


def test_ambiguous_labels_are_rejected_instead_of_guessed(monkeypatch):
    class FakeConfig:
        id2label = {0: "class_a", 1: "class_b"}  # لا يوجد اسم واضح لـ«مزيف»

    class FakeModel:
        config = FakeConfig()

        def to(self, device):
            return self

        def eval(self):
            return self

    def fake_snapshot_download(*args, **kwargs):
        return "/tmp/fake-model-path"

    def fake_from_pretrained(*args, **kwargs):
        return FakeModel()

    import app.registry as registry_module

    monkeypatch.setattr("huggingface_hub.snapshot_download", fake_snapshot_download)
    monkeypatch.setattr("transformers.AutoImageProcessor.from_pretrained", fake_from_pretrained)
    monkeypatch.setattr("transformers.AutoModelForImageClassification.from_pretrained", fake_from_pretrained)
    monkeypatch.setattr("os.path.exists", lambda p: True)

    reg = Registry()
    spec = ModelSpec(
        key="ambiguous", hf_id="test/ambiguous", media_type="image", task="image-classification",
        license="mit", description="test", fake_labels=frozenset({"fake"}),
    )
    monkeypatch.setitem(registry_module.SPECS, "ambiguous", spec)

    with pytest.raises(ModelUnavailable):
        reg.get("ambiguous")

    status = {r["key"]: r for r in reg.status()}["ambiguous"]
    assert status["loaded"] is False
    assert status["error"] is not None


def test_unknown_model_key_is_reported_as_unavailable_not_a_crash():
    """أي فشل تحميل، بما فيه مفتاح غير معروف، يتحول إلى ModelUnavailable (-> 503) لا استثناء يُسقط الخدمة."""
    reg = Registry()
    with pytest.raises(ModelUnavailable):
        reg.get("does_not_exist")
