# مخطط قاعدة البيانات (ERD)

12 جدولًا مترابطة. التفاصيل الكاملة للأعمدة في ملفات الترحيل بمجلد `web/database/migrations`.

```mermaid
erDiagram
    ROLES ||--o{ USERS : "له"
    USERS ||--o{ MEDIA_FILES : "يرفع"
    USERS ||--o{ VERIFICATION_REQUESTS : "يطلب"
    USERS ||--o{ SHARED_REPORTS : "ينشئ"
    USERS ||--o{ USER_NOTIFICATIONS : "يستقبل"
    USERS ||--o{ ACTIVITY_LOGS : "ينفّذ"
    MEDIA_FILES ||--|| VERIFICATION_REQUESTS : "يخص"
    VERIFICATION_REQUESTS ||--o| VERIFICATION_RESULTS : "ينتج"
    VERIFICATION_REQUESTS ||--o{ SHARED_REPORTS : "يُشارَك عبر"
    VERIFICATION_REQUESTS |o--o{ VERIFICATION_REQUESTS : "reused_from"
    VERIFICATION_RESULTS ||--o{ DETECTED_INDICATORS : "يحتوي"
    MODEL_VERSIONS ||--o{ VERIFICATION_RESULTS : "أنتج بواسطة"

    ROLES {
        bigint id PK
        string slug "admin | user"
        string name_ar
        string name_en
    }

    USERS {
        bigint id PK
        bigint role_id FK
        string name
        string email UK
        string password "مشفّر bcrypt"
        string status "active | suspended"
        string locale
        string account_type
        timestamp last_login_at
        timestamp deleted_at "حذف منطقي"
    }

    MEDIA_FILES {
        bigint id PK
        bigint user_id FK
        string media_type "image|video|audio"
        string original_name
        string stored_name UK "اسم عشوائي على القرص"
        string disk
        string path
        string mime_type
        char sha256 "بصمة الملف، مفهرسة"
        int width
        int height
        decimal duration_seconds
        json metadata
        timestamp consented_at
        timestamp expires_at "الاحتفاظ التلقائي"
        timestamp file_deleted_at
        timestamp deleted_at
    }

    VERIFICATION_REQUESTS {
        bigint id PK
        string reference_number UK "AMV-YYYY-XXXXXXXX"
        bigint user_id FK
        bigint media_file_id FK
        string status "pending|uploading|analyzing|completed|failed|needs_review"
        tinyint progress
        string stage
        bigint reused_from_id FK "طلب سابق بالبصمة نفسها"
        string error_message
        boolean is_demo
        timestamp completed_at
        timestamp deleted_at
    }

    VERIFICATION_RESULTS {
        bigint id PK
        bigint verification_request_id FK UK
        string classification "real|uncertain|fake"
        decimal confidence_score
        decimal authenticity_probability
        decimal manipulation_probability
        string risk_level "low|medium|high"
        text explanation
        bigint model_version_id FK
        string model_name
        string model_version
        decimal processing_time
        json thresholds "الحدود وقت التصنيف"
        json raw_response
        timestamp completed_at
    }

    DETECTED_INDICATORS {
        bigint id PK
        bigint verification_result_id FK
        string code "رمز ثابت، يُترجم في lang/"
        string category
        string severity "info|low|medium|high"
        decimal score
        decimal timestamp_start
        decimal timestamp_end
        string frame_path
        json details
    }

    MODEL_VERSIONS {
        bigint id PK
        string name
        string media_type
        string version
        string source_url
        string license
        boolean is_active
        json metrics "by_dataset: مقاييس تقييم حقيقية"
        string evaluation_dataset
        timestamp evaluated_at
    }

    SHARED_REPORTS {
        bigint id PK
        bigint verification_request_id FK
        bigint user_id FK
        char token_hash UK "SHA-256 للبحث"
        text token_encrypted "الرمز الفعلي، مشفَّر"
        boolean hide_owner
        boolean show_preview
        int views_count
        timestamp expires_at
        timestamp revoked_at
    }

    USER_NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        string type
        string title
        text body
        string url
        timestamp read_at
    }

    SYSTEM_SETTINGS {
        bigint id PK
        string key UK
        text value
        string type "string|int|float|bool|json"
        string group
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string subject_type "polymorphic"
        bigint subject_id
        string description
        json properties
        string ip_address
        timestamp created_at "بلا updated_at، سجل غير قابل للتعديل"
    }
```

## ملاحظات تصميمية

- **الحذف المنطقي** (`SoftDeletes`) على `users` و`media_files` و`verification_requests`، فتبقى سجلات التدقيق موجودة بعد حذف المستخدم لحسابه.
- **إعادة الاستخدام بالبصمة**: `verification_requests.reused_from_id` يشير إلى طلب سابق بالملف نفسه (SHA-256) حين يُعاد تحليله فعليًا، فلا يُعاد استدعاء خدمة الذكاء الاصطناعي.
- **حساب الزائر النظامي**: تحليلات الزوار غير المسجَّلين (بلا حساب) تُنسب إلى حساب داخلي واحد ثابت (`users.email = guest@aimv.internal`, `status = suspended` فلا يمكنه تسجيل الدخول)، وتُميَّز عنه ملكية النتيجة الفعلية عبر جلسة المتصفح لا عبر قاعدة البيانات — تفاصيلها في [ARCHITECTURE.md](ARCHITECTURE.md).
- **`shared_reports.token_hash`** فهرس بحث بصمة الرمز، و`token_encrypted` نسخة مشفّرة يستطيع صاحب الحساب وحده استرجاعها لنسخ الرابط؛ لا يُخزَّن الرمز نصًا واضحًا في أي عمود.
- **`model_versions.metrics`** يخزّن مقاييس تقييم حقيقية مفهرسة بمعرّف مجموعة بيانات الاختبار (`by_dataset`)، لأن نموذجًا مركّبًا واحدًا (مثل نموذج الصور) قد يُقاس على أكثر من مجموعة بيانات تفحص قدرات مختلفة له.
