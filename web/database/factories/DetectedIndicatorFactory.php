<?php

namespace Database\Factories;

use App\Enums\IndicatorSeverity;
use App\Models\DetectedIndicator;
use App\Models\VerificationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetectedIndicator>
 */
class DetectedIndicatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'verification_result_id' => VerificationResult::factory(),
            'code' => 'ela_anomaly',
            'category' => 'compression',
            'severity' => IndicatorSeverity::Medium,
            'score' => 55.0,
            'description' => null,
        ];
    }
}
