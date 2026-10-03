# تسلسل عملية التحقق الكاملة

يغطي هذا المخطط رحلة مستخدم مسجَّل من رفع الملف حتى ظهور النتيجة. تدفّق الزائر بلا حساب مطابق تقريبًا، باستثناء أن المهمة تُعالج فور الرفع (قد تكون متزامنة حسب إعداد الطابور) وتنتهي في صفحة نتيجة محدودة بلا خيار تنزيل.

```mermaid
sequenceDiagram
    actor U as المستخدم
    participant W as المتصفح
    participant L as Laravel (Controller)
    participant I as VerificationIngestor
    participant Q as طابور المهام
    participant J as AnalyzeMediaJob
    participant A as AiClient
    participant F as خدمة FastAPI
    participant M as النموذج (PyTorch)
    participant R as ResultRecorder
    participant DB as قاعدة البيانات

    U->>W: يختار نوع الملف ويرفعه + موافقة المعالجة
    W->>L: POST /verifications (multipart)
    L->>I: ingest(user, file, type)
    I->>I: فحص الامتداد وMIME الفعلي والحجم
    alt الملف مرفوض
        I-->>L: ValidationException
        L-->>W: خطأ 422 برسالة عربية واضحة
    else الملف مقبول
        I->>I: حفظ باسم عشوائي في قرص خاص
        I->>I: حساب بصمة SHA-256
        I->>DB: إنشاء media_files + verification_requests (pending)
        alt بصمة مطابقة لتحليل سابق بنموذج نشط
            I->>R: recordReused(request, source)
            R->>DB: نسخ النتيجة والمؤشرات (completed)
        else لا يوجد تحليل سابق صالح
            I->>Q: نشر AnalyzeMediaJob
        end
        I-->>L: VerificationRequest
        L-->>W: توجيه إلى صفحة التقدم
    end

    W->>L: GET /verifications/{ref}/status (استطلاع كل 2 ثانية)
    L-->>W: JSON {status, progress, stage}

    Q->>J: تنفيذ المهمة
    J->>DB: تحديث الحالة إلى analyzing
    J->>A: analyze(mediaFile)
    A->>F: POST /v1/analyze (ملف + X-API-Key)

    alt المفتاح غير صالح
        F-->>A: 401
    else الخدمة بلا نموذج متاح
        F-->>A: 503
    else طلب سليم
        F->>M: تشغيل النموذج (أو مجموعة نماذج)
        M-->>F: احتمال التلاعب + درجات المؤشرات
        F-->>A: 200 JSON (منظَّم بمخطط AnalysisResponse)
    end

    alt الخدمة غير متاحة / رفضت / استجابة غير صالحة
        A-->>J: AiServiceException
        J->>DB: status=failed, error_message=رمز آمن
        J->>U: إشعار فشل التحليل
    else نجح التحليل
        A-->>J: AiAnalysis (مُتحقَّق من شكله ونطاقه)
        J->>R: record(request, analysis)
        R->>DB: تصنيف حسب حدود system_settings الحالية
        R->>DB: حفظ verification_results + detected_indicators
        R->>DB: حفظ صور الإطارات المشبوهة (فيديو)
        R->>U: إشعار اكتمال التحليل
    end

    W->>L: GET /verifications/{ref}/status
    L-->>W: {final: true, url: .../show}
    W->>L: GET /verifications/{ref}
    L-->>W: صفحة النتيجة (تصنيف + احتمالات + مؤشرات + تنبيه الحدود)
```

## نقاط أمان مضمَّنة في التسلسل

- **لا نتيجة بلا تحليل حقيقي**: أي فشل في أي نقطة (شبكة، رفض، استجابة مشوَّهة) ينتهي بحالة `failed` صريحة، لا بدرجة مُخترَعة.
- **إعادة الاستخدام مشروطة بنموذج نشط**: لا تُعاد نتيجة نموذج قديم أُلغي تفعيله من لوحة المدير.
- **الحدود تُقرأ لحظة التصنيف** وتُحفظ مع النتيجة (`thresholds` JSON)، فتغيير المدير للحدود لاحقًا لا يُعيد تصنيف النتائج القديمة بأثر رجعي.
