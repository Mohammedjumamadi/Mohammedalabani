<?php

namespace App\Models;

use App\Enums\Classification;
use App\Enums\RiskLevel;
use Database\Factories\VerificationResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\MediaType;
use App\Services\Verification\ExplanationBuilder;

class VerificationResult extends Model
{
    /** @use HasFactory<VerificationResultFactory> */
    use HasFactory;

    protected $fillable = [
        'verification_request_id',
        'classification',
        'confidence_score',
        'authenticity_probability',
        'manipulation_probability',
        'risk_level',
        'explanation',
        'model_version_id',
        'model_name',
        'model_version',
        'processing_time',
        'thresholds',
        'raw_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'classification' => Classification::class,
            'risk_level' => RiskLevel::class,
            'confidence_score' => 'float',
            'authenticity_probability' => 'float',
            'manipulation_probability' => 'float',
            'processing_time' => 'float',
            'thresholds' => 'array',
            'raw_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(DetectedIndicator::class);
    }

    /**
     * الشرح المعروض: بيانات العرض التجريبي تحتفظ بنصها الثابت الموسوم،
     * أما النتائج الحقيقية فيُبنى شرحها من التصنيف والمؤشرات بلغة الواجهة الحالية.
     */
    public function displayExplanation(MediaType $type): string
    {
        if (($this->raw_response['demo'] ?? false) === true) {
            return (string) $this->explanation;
        }

        return (new ExplanationBuilder)->build($this->loadMissing('indicators'), $type);
    }
}
