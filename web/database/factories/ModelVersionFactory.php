<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelVersion>
 */
class ModelVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'test-model-'.fake()->unique()->numberBetween(1, 9999),
            'media_type' => MediaType::Image,
            'version' => '1.0.0',
            'source_url' => null,
            'license' => 'test',
            'description' => 'نموذج للاختبارات فقط.',
            'is_active' => false,
            'metrics' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
