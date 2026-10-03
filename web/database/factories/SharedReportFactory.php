<?php

namespace Database\Factories;

use App\Models\SharedReport;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SharedReport>
 */
class SharedReportFactory extends Factory
{
    public function definition(): array
    {
        $token = Str::random(48);

        return [
            'verification_request_id' => VerificationRequest::factory(),
            'user_id' => fn (array $attributes) => VerificationRequest::find($attributes['verification_request_id'])->user_id,
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'hide_owner' => true,
            'show_preview' => false,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
