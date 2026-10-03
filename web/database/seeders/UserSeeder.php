<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * حسابات تجريبية للتطوير والعرض فقط. يجب تغيير كلمات المرور أو حذف الحسابين عند أي نشر فعلي.
 */
class UserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@aimv.test';

    public const ADMIN_PASSWORD = 'Admin@12345';

    public const USER_EMAIL = 'user@aimv.test';

    public const USER_PASSWORD = 'User@12345';

    public function run(): void
    {
        if (! User::where('email', self::ADMIN_EMAIL)->exists()) {
            User::factory()->admin()->create([
                'name' => 'مدير النظام التجريبي',
                'email' => self::ADMIN_EMAIL,
                'password' => self::ADMIN_PASSWORD,
                'account_type' => null,
            ]);
        }

        if (! User::where('email', self::USER_EMAIL)->exists()) {
            User::factory()->create([
                'name' => 'مستخدم تجريبي',
                'email' => self::USER_EMAIL,
                'password' => self::USER_PASSWORD,
                'account_type' => 'journalist',
                'organization' => 'مؤسسة تجريبية',
            ]);
        }
    }
}
