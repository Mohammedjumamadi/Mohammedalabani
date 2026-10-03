<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    public function definition(): array
    {
        $stored = Str::random(40);

        return [
            'user_id' => User::factory(),
            'media_type' => MediaType::Image,
            'original_name' => 'sample-'.fake()->numberBetween(1, 999).'.jpg',
            'stored_name' => $stored.'.jpg',
            'disk' => 'private',
            'path' => 'uploads/'.$stored.'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => fake()->numberBetween(80_000, 4_000_000),
            'sha256' => hash('sha256', $stored),
            'width' => 1280,
            'height' => 720,
            'consented_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'media_type' => MediaType::Video,
            'original_name' => 'clip-'.fake()->numberBetween(1, 999).'.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => fake()->numberBetween(2_000_000, 60_000_000),
            'duration_seconds' => fake()->randomFloat(2, 5, 120),
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn () => [
            'media_type' => MediaType::Audio,
            'original_name' => 'voice-'.fake()->numberBetween(1, 999).'.mp3',
            'mime_type' => 'audio/mpeg',
            'extension' => 'mp3',
            'size_bytes' => fake()->numberBetween(200_000, 9_000_000),
            'width' => null,
            'height' => null,
            'duration_seconds' => fake()->randomFloat(2, 3, 300),
        ]);
    }
}
