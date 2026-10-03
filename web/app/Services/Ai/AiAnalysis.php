<?php

namespace App\Services\Ai;

use App\Enums\IndicatorSeverity;
use App\Enums\MediaType;

/**
 * نتيجة تحليل صادرة عن خدمة الذكاء الاصطناعي بعد التحقق من شكلها ونطاق قيمها.
 * لا تُنشأ إلا من استجابة حقيقية للخدمة عبر fromArray().
 */
final class AiAnalysis
{
    /**
     * @param  list<array<string, mixed>>  $indicators
     * @param  list<array<string, mixed>>  $frames  إطارات مشبوهة بصيغة base64 JPEG
     * @param  array<string, mixed>  $technical
     * @param  array<string, mixed>  $raw  الاستجابة بلا الصور
     */
    public function __construct(
        public readonly MediaType $mediaType,
        public readonly float $manipulationProbability,
        public readonly float $confidence,
        public readonly string $modelName,
        public readonly string $modelVersion,
        public readonly ?string $modelSource,
        public readonly ?string $modelLicense,
        public readonly float $processingTime,
        public readonly array $indicators,
        public readonly array $frames,
        public readonly array $technical,
        public readonly array $raw,
    ) {}

    public function authenticityProbability(): float
    {
        return round(100 - $this->manipulationProbability, 2);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $manipulation = $data['manipulation_probability'] ?? null;
        $model = $data['model'] ?? null;

        if (! is_numeric($manipulation) || $manipulation < 0 || $manipulation > 100
            || ! is_array($model) || empty($model['name']) || empty($model['version'])
            || MediaType::tryFrom((string) ($data['media_type'] ?? '')) === null) {
            throw new AiServiceException(AiServiceException::INVALID_RESPONSE, 'Malformed analysis payload');
        }

        $manipulation = round((float) $manipulation, 2);
        $confidence = isset($data['confidence']) && is_numeric($data['confidence'])
            ? min(100.0, max(0.0, (float) $data['confidence']))
            : max($manipulation, 100 - $manipulation);

        $indicators = [];
        foreach ((array) ($data['indicators'] ?? []) as $indicator) {
            if (! is_array($indicator) || empty($indicator['code'])) {
                continue;
            }
            $indicators[] = [
                'code' => mb_substr((string) $indicator['code'], 0, 60),
                'category' => mb_substr((string) ($indicator['category'] ?? 'general'), 0, 30),
                'severity' => IndicatorSeverity::tryFrom((string) ($indicator['severity'] ?? 'info'))?->value ?? 'info',
                'score' => isset($indicator['score']) && is_numeric($indicator['score']) ? round((float) $indicator['score'], 2) : null,
                'description' => isset($indicator['description']) ? mb_substr((string) $indicator['description'], 0, 500) : null,
                'timestamp_start' => isset($indicator['timestamp_start']) && is_numeric($indicator['timestamp_start']) ? (float) $indicator['timestamp_start'] : null,
                'timestamp_end' => isset($indicator['timestamp_end']) && is_numeric($indicator['timestamp_end']) ? (float) $indicator['timestamp_end'] : null,
                'frame_index' => isset($indicator['frame_index']) && is_numeric($indicator['frame_index']) ? (int) $indicator['frame_index'] : null,
                'details' => is_array($indicator['details'] ?? null) ? $indicator['details'] : null,
            ];
        }

        $frames = [];
        foreach ((array) ($data['frames'] ?? []) as $frame) {
            if (is_array($frame) && isset($frame['index'], $frame['image_b64'])) {
                $frames[] = [
                    'index' => (int) $frame['index'],
                    'timestamp' => isset($frame['timestamp']) ? (float) $frame['timestamp'] : null,
                    'suspicion' => isset($frame['suspicion']) ? (float) $frame['suspicion'] : null,
                    'image_b64' => (string) $frame['image_b64'],
                ];
            }
        }

        $raw = $data;
        unset($raw['frames']);

        return new self(
            mediaType: MediaType::from($data['media_type']),
            manipulationProbability: $manipulation,
            confidence: round($confidence, 2),
            modelName: mb_substr((string) $model['name'], 0, 150),
            modelVersion: mb_substr((string) $model['version'], 0, 50),
            modelSource: isset($model['source']) ? mb_substr((string) $model['source'], 0, 255) : null,
            modelLicense: isset($model['license']) ? mb_substr((string) $model['license'], 0, 100) : null,
            processingTime: round((float) ($data['processing_time'] ?? 0), 2),
            indicators: $indicators,
            frames: $frames,
            technical: is_array($data['technical'] ?? null) ? $data['technical'] : [],
            raw: $raw,
        );
    }
}
