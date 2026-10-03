# توثيق REST API — خدمة الذكاء الاصطناعي

الرابط الأساسي: `AI_SERVICE_URL` (افتراضيًا `http://127.0.0.1:8001`). توثيق تفاعلي تلقائي (Swagger UI) متاح على `/docs` عند تشغيل الخدمة.

## المصادقة

كل نقاط النهاية عدا `/health` تتطلب ترويسة:

```
X-API-Key: <القيمة نفسها في AI_SERVICE_KEY على الطرفين>
```

طلب بلا المفتاح أو بمفتاح خاطئ يُرفض بـ `401 Unauthorized`. إن لم يكن المفتاح مضبوطًا على الخدمة أصلًا، تُرفض كل الطلبات بـ `503` (فشل آمن، لا خدمة مفتوحة بالخطأ).

## `GET /health`

بلا مصادقة. حالة الخدمة والنماذج.

```json
{
  "status": "ok",
  "service": "aimv-ai-service",
  "version": "1.0.0",
  "device": "cpu",
  "ffmpeg": true,
  "models": [
    {"key": "image_face", "media_type": "image", "hf_id": "prithivMLmods/Deep-Fake-Detector-v2-Model", "license": "apache-2.0", "loaded": true, "error": null, "version": "3a99ae26f5"}
  ]
}
```

`status` يصبح `"degraded"` إن كان FFmpeg غير مثبَّت أو فشل تحميل أي نموذج.

## `GET /models`

يتطلب المفتاح. قائمة النماذج المسجَّلة ومصادرها وتراخيصها (لعرضها في لوحة المدير).

## `POST /v1/analyze`

يتطلب المفتاح. `multipart/form-data`:

| الحقل | النوع | الوصف |
|---|---|---|
| `file` | ملف | الصورة/الفيديو/الصوت |
| `media_type` | نص | `image` أو `video` أو `audio` |

**استجابة ناجحة (200)** — مطابقة لمخطط `AnalysisResponse`:

```json
{
  "media_type": "image",
  "manipulation_probability": 39.59,
  "authenticity_probability": 60.41,
  "confidence": 60.41,
  "model": {
    "name": "aimv-image-ensemble",
    "version": "1.0.0 [3a99ae26f5+c7e223baf1]",
    "source": "https://huggingface.co/prithivMLmods/Deep-Fake-Detector-v2-Model, https://huggingface.co/umm-maybe/AI-image-detector",
    "license": "apache-2.0, cc-by-4.0",
    "components": [{"key": "image_face", "hf_id": "...", "revision": "3a99ae26f5", "license": "apache-2.0", "task": "image-classification"}]
  },
  "processing_time": 2.4,
  "indicators": [
    {"code": "model_deepfake_score", "category": "model", "severity": "medium", "score": 58.08, "description": null,
     "timestamp_start": null, "timestamp_end": null, "frame_index": null, "details": {"analyzed": "whole_image"}}
  ],
  "frames": [],
  "technical": {"width": 300, "height": 300, "format": "JPEG", "faces_detected": 0}
}
```

| رمز الخطأ | السبب | كيف تعامله Laravel |
|---|---|---|
| `401` | مفتاح مفقود/خاطئ | `AiServiceException::UNAVAILABLE` (لا يُعرض تفصيل للمستخدم) |
| `413` | الملف أكبر من `AI_MAX_UPLOAD_MB` | يُترجم إلى فشل مع رسالة آمنة |
| `422` | امتداد لا يطابق `media_type`، أو ملف تالف يتعذّر فك ترميزه | `MediaError` من المحلل، رسالة تشرح السبب دون تفاصيل تقنية |
| `503` | لا نموذج متاح لهذا النوع (فشل تحميل أو غير مُهيَّأ) | `AiServiceException::NO_MODEL`، رسالة «لا يوجد نموذج جاهز حاليًا» |
| اتصال مرفوض/مهلة | الخدمة متوقفة أو بطيئة | `AiServiceException::UNAVAILABLE`/`TIMEOUT`، الطلب يُعاد للطابور مرة واحدة ثم يفشل بأمان |

### مخطط المؤشر (Indicator)

| الحقل | الوصف |
|---|---|
| `code` | رمز ثابت (مثل `model_deepfake_score`, `ela_anomaly`)، يُترجم في `web/lang/{ar,en}/indicators.php` |
| `category` | `model` (من نموذج ذكاء اصطناعي حقيقي) أو `visual`/`compression`/`metadata`/`temporal`/`audio` (فحص مساعد) |
| `severity` | `info` \| `low` \| `medium` \| `high` |
| `score` | 0–100 أو `null` |
| `details.experimental` | `true` إن كان المؤشر قياسًا لم تُقيَّم دقته إحصائيًا؛ لا يدخل في `manipulation_probability` |

**مبدأ أساسي**: `manipulation_probability` يصدر **فقط** عن مخرجات النموذج (أو تجميع نماذج). المؤشرات المساعدة (ELA، البيانات الوصفية، تزامن الشفاه...) تُعرض للشفافية ولا تُغيّر هذا الاحتمال، باستثناء حالة واحدة معلنة صراحة في الشيفرة: دليل مباشر على أداة توليد صور في البيانات الوصفية يرفع الاحتمال إلى حد أدنى ثابت (90%) لأنه دليل قاطع لا إحصائي.

## عقد الثقة بين الطرفين

- Laravel **لا يثق** بأي حقل غير موجود أو خارج نطاقه في الاستجابة؛ `App\Services\Ai\AiAnalysis::fromArray()` يتحقق من كل حقل ويرفض (`AiServiceException::INVALID_RESPONSE`) أي استجابة مشوَّهة بدل عرضها كنتيجة.
- الخدمة لا تثق بامتداد الملف وحده؛ `main.py` يتحقق أن الامتداد يطابق `media_type` المُعلن، والمحلل نفسه يرفض ملفًا لا يمكن فك ترميزه فعليًا.
