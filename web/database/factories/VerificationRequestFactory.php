<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationRequest>
 */
class VerificationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // ملف الوسائط يخص المستخدم نفسه صاحب الطلب
            'media_file_id' => fn (array $attributes) => MediaFile::factory()->create(['user_id' => $attributes['user_id']])->id,
            'status' => RequestStatus::Pending,
            'progress' => 0,
            'is_demo' => false,
        ];
    }

    public function status(RequestStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'progress' => $status->isFinal() && $status !== RequestStatus::Failed ? 100 : 0,
            'completed_at' => $status->isFinal() ? now() : null,
        ]);
    }

    public function failed(string $message = 'تعذّر إكمال التحليل.'): static
    {
        return $this->status(RequestStatus::Failed)->state(fn () => ['error_message' => $message]);
    }

    public function demo(): static
    {
        return $this->state(fn () => ['is_demo' => true]);
    }
}
