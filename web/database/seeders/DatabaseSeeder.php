<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SystemSettingSeeder::class,
            ContentSeeder::class,
            UserSeeder::class,
        ]);

        // بيانات العرض التجريبي تُنشأ في بيئة التطوير المحلية فقط
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
