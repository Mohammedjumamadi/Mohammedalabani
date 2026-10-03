<?php

namespace Tests\Feature\Database;

use App\Enums\Classification;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_roles_settings_and_demo_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Role::count());
        $this->assertSame(70, SystemSetting::get('threshold_fake'));
        $this->assertSame(30, SystemSetting::get('threshold_real'));

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();
        $user = User::where('email', UserSeeder::USER_EMAIL)->first();
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(Hash::check(UserSeeder::ADMIN_PASSWORD, $admin->password));
        $this->assertNotSame(UserSeeder::ADMIN_PASSWORD, $admin->password);
    }

    public function test_demo_data_is_not_seeded_outside_local_environment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, VerificationRequest::count());
    }

    public function test_seeding_twice_does_not_duplicate_accounts_or_overwrite_admin_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        SystemSetting::put('threshold_fake', 85);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', UserSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(85, SystemSetting::get('threshold_fake'));
    }

    public function test_demo_data_is_flagged_labelled_and_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThan(0, VerificationRequest::count());
        $this->assertSame(0, VerificationRequest::where('is_demo', false)->count(), 'كل السجلات التجريبية يجب أن تُوسم');

        foreach (VerificationResult::all() as $result) {
            $this->assertSame(
                Classification::fromManipulationProbability($result->manipulation_probability, 70, 30),
                $result->classification,
            );
            $this->assertEqualsWithDelta(100, $result->authenticity_probability + $result->manipulation_probability, 0.01);
            $this->assertStringContainsString('بيانات عرض تجريبي', $result->explanation);
            $this->assertNull($result->model_version_id, 'البيانات التجريبية لا تُنسب إلى نموذج حقيقي');
        }
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $count = VerificationRequest::count();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($count, VerificationRequest::count());
    }
}
