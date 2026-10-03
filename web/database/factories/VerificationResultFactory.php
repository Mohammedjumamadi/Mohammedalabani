<?php

namespace Database\Factories;

use App\Enums\Classification;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * مصنع للاختبارات فقط: يبني نتيجة متسقة رياضيًا من احتمال تلاعب معيّن.
 * لا يُستخدم لتوليد نتائج تظهر للمستخدم على أنها صادرة عن نموذج حقيقي.
 *
 * @extends Factory<VerificationResult>
 */
class VerificationResultFactory extends Factory
{
    public function definition(): array
    {
        return ['verification_request_id' => VerificationRequest::factory()] + $this->attributesFor(50.0);
    }

    /** يبني القيم المشتقة كلها من احتمال التلاعب لضمان التناسق. */
    public function forManipulation(float $manipulation): static
    {
        return $this->state(fn () => $this->attributesFor($manipulation));
    }

    /** @return array<string, mixed> */
    private function attributesFor(float $manipulation): array
    {
        $classification = Classification::fromManipulationProbability($manipulation, 70, 30);

        return [
            'classification' => $classification,
            'manipulation_probability' => $manipulation,
            'authenticity_probability' => round(100 - $manipulation, 2),
            'confidence_score' => round(max($manipulation, 100 - $manipulation), 2),
            'risk_level' => $classification->riskLevel(),
            'explanation' => 'نتيجة اختبار آلي.',
            'model_name' => 'test-model',
            'model_version' => '0.0.0-test',
            'processing_time' => 1.25,
            'thresholds' => ['fake' => 70, 'real' => 30],
            'raw_response' => ['source' => 'factory'],
            'completed_at' => now(),
        ];
    }
}
