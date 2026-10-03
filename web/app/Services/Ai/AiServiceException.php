<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * خطأ في التواصل مع خدمة الذكاء الاصطناعي. الرمز $reason هو ما يُعرض للمستخدم (مترجمًا)،
 * أما التفاصيل التقنية فتُسجَّل في السجلات فقط ولا تظهر في الواجهة.
 */
class AiServiceException extends RuntimeException
{
    public const UNAVAILABLE = 'service_unavailable';

    public const TIMEOUT = 'timeout';

    public const REJECTED = 'rejected';

    public const INVALID_RESPONSE = 'invalid_response';

    public const NO_MODEL = 'no_model';

    public function __construct(public readonly string $reason, string $technicalMessage = '', public readonly bool $retryable = false)
    {
        parent::__construct($technicalMessage ?: $reason);
    }
}
