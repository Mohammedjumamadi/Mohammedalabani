<?php

/*
|--------------------------------------------------------------------------
| إعدادات منصة AI Media Verifier
|--------------------------------------------------------------------------
| القيم الواردة هنا قيم افتراضية فقط. يستطيع المدير تعديل الحدود والأنواع
| المسموحة من لوحة التحكم، وتُخزَّن هناك في جدول system_settings.
*/

return [

    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
        'en' => ['name' => 'English', 'dir' => 'ltr'],
    ],

    // أنواع الحسابات (الفئات المستهدفة) عند التسجيل
    'account_types' => [
        'general', 'social_media', 'journalist', 'content_creator',
        'student', 'researcher', 'institution', 'media_org',
    ],

    // أنواع الوسائط المدعومة: الامتدادات وأنواع MIME المقبولة (تُفحص من محتوى الملف لا من اسمه)
    'media' => [
        'disk' => 'private',
        'types' => [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'video' => [
                'extensions' => ['mp4', 'mov', 'avi', 'webm', 'mkv'],
                'mimes' => ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/avi', 'video/webm', 'video/x-matroska'],
            ],
            'audio' => [
                'extensions' => ['mp3', 'wav', 'm4a', 'ogg', 'flac'],
                'mimes' => [
                    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/x-m4a', 'audio/mp4',
                    'audio/ogg', 'application/ogg', 'audio/flac', 'audio/x-flac',
                ],
            ],
        ],
        // لا يُسمح بها إطلاقًا حتى لو أضافها المدير بالخطأ
        'blocked_extensions' => [
            'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'scr', 'ps1', 'psm1', 'sh', 'bash', 'php', 'phtml', 'phar', 'php3', 'php4', 'php5',
            'js', 'mjs', 'jar', 'vbs', 'wsf', 'html', 'htm', 'svg', 'py', 'pl', 'rb', 'cgi', 'asp', 'aspx', 'jsp', 'lnk', 'reg', 'apk',
        ],
        // أقصى أبعاد للصور (حماية من قنابل فك الضغط)
        'max_image_pixels' => 100_000_000,
        'max_frames_stored' => 12,
        'max_frame_bytes' => 600_000,
        'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    ],

    // تجربة الزائر بلا حساب: يستطيع الزائر رفع ملف واحد ورؤية النتيجة، لكن تنزيل التقرير ومشاركته يتطلبان حسابًا
    'guest' => [
        'enabled' => (bool) env('AIMV_GUEST_UPLOADS', true),
        // مدة احتفاظ قصيرة جدًا مقارنة بالمستخدمين المسجَّلين، لأن الملف لا يملكه حساب حقيقي
        'retention_hours' => (int) env('AIMV_GUEST_RETENTION_HOURS', 6),
    ],

    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'key' => env('AI_SERVICE_KEY'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 300),
    ],

    // القيم الافتراضية للإعدادات القابلة للتعديل من لوحة المدير
    'defaults' => [
        'threshold_fake' => 70,      // احتمال التلاعب ≥ هذه القيمة: مزيف على الأرجح
        'threshold_real' => 30,      // احتمال التلاعب < هذه القيمة: حقيقي على الأرجح
        'max_upload_mb' => (int) env('UPLOAD_MAX_MB', 100),
        'retention_days' => (int) env('RETENTION_DAYS', 30),
    ],
];
