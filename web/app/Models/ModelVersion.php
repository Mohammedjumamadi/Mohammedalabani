<?php

namespace App\Models;

use App\Enums\MediaType;
use Database\Factories\ModelVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelVersion extends Model
{
    /** @use HasFactory<ModelVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'media_type',
        'version',
        'source_url',
        'license',
        'description',
        'is_active',
        'metrics',
        'evaluation_dataset',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'is_active' => 'boolean',
            'metrics' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(VerificationResult::class);
    }

    /** هل توجد مقاييس تقييم حقيقية مسجلة لهذا النموذج؟ */
    public function hasMetrics(): bool
    {
        return ! empty($this->metrics['by_dataset']);
    }

    /**
     * مقاييس التقييم مفهرسة بمعرّف مجموعة البيانات؛ نموذج مركّب واحد قد يُقاس على أكثر من مجموعة
     * تفحص قدرات مختلفة له (كشف الوجوه المزيفة مقابل كشف الصور المولّدة مثلًا).
     *
     * @return array<string, array<string, mixed>>
     */
    public function metricsByDataset(): array
    {
        return $this->metrics['by_dataset'] ?? [];
    }
}
