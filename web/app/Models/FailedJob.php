<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** سجل المهام الفاشلة في الطابور (جدول failed_jobs القياسي في Laravel) للعرض في لوحة المدير. */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $table = 'failed_jobs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['failed_at' => 'datetime'];
    }

    /** اسم الفئة فقط دون تفاصيل الحمولة الحساسة. */
    public function jobName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? 'unknown';
    }
}
