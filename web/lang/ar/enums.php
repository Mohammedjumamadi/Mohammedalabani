<?php

return [
    'media_type' => [
        'image' => 'صورة',
        'video' => 'فيديو',
        'audio' => 'صوت',
    ],

    'request_status' => [
        'pending' => 'في انتظار التحليل',
        'uploading' => 'جارٍ رفع الملف',
        'analyzing' => 'جارٍ تحليل المحتوى',
        'completed' => 'اكتمل التحليل',
        'failed' => 'فشل التحليل',
        'needs_review' => 'يحتاج إلى مراجعة بشرية',
    ],

    'classification' => [
        'real' => 'حقيقي على الأرجح',
        'uncertain' => 'نتيجة غير حاسمة',
        'fake' => 'مزيف على الأرجح',
    ],

    'risk_level' => [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'مرتفع',
    ],

    'severity' => [
        'info' => 'معلومة',
        'low' => 'ضعيف',
        'medium' => 'متوسط',
        'high' => 'قوي',
    ],

    'user_status' => [
        'active' => 'نشط',
        'suspended' => 'معلّق',
    ],

    'account_type' => [
        'general' => 'مستخدم عام',
        'social_media' => 'مستخدم وسائل تواصل اجتماعي',
        'journalist' => 'صحفي / إعلامي',
        'content_creator' => 'صانع محتوى',
        'student' => 'طالب',
        'researcher' => 'باحث',
        'institution' => 'مؤسسة تعليمية',
        'media_org' => 'مؤسسة إعلامية',
    ],
];
