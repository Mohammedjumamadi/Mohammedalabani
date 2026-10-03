<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'auth.login',
            'description' => 'تسجيل دخول',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory',
        ];
    }
}
