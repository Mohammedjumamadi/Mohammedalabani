<?php

namespace App\Models;

use App\Enums\IndicatorSeverity;
use Database\Factories\DetectedIndicatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

class DetectedIndicator extends Model
{
    /** @use HasFactory<DetectedIndicatorFactory> */
    use HasFactory;

    protected $fillable = [
        'verification_result_id',
        'code',
        'category',
        'severity',
        'score',
        'description',
        'timestamp_start',
        'timestamp_end',
        'frame_path',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IndicatorSeverity::class,
            'score' => 'float',
            'timestamp_start' => 'float',
            'timestamp_end' => 'float',
            'details' => 'array',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(VerificationResult::class, 'verification_result_id');
    }

    /** عنوان المؤشر المترجم؛ يعود إلى الرمز نفسه إذا لم تتوفر ترجمة. */
    public function title(): string
    {
        $key = 'indicators.'.$this->code.'.title';

        return Lang::has($key) ? __($key) : $this->code;
    }

    /** شرح المؤشر المبسط للمستخدم. */
    public function explanation(): ?string
    {
        $key = 'indicators.'.$this->code.'.help';

        return Lang::has($key) ? __($key) : $this->description;
    }
}
