<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = config('aimv.defaults');

        $settings = [
            // حدود التصنيف: احتمال التلاعب ≥ fake مزيف على الأرجح، و< real حقيقي على الأرجح، وما بينهما غير حاسم
            ['threshold_fake', $defaults['threshold_fake'], 'int', 'classification'],
            ['threshold_real', $defaults['threshold_real'], 'int', 'classification'],

            ['max_upload_mb', $defaults['max_upload_mb'], 'int', 'uploads'],
            ['allowed_extensions', [
                'image' => ['jpg', 'jpeg', 'png', 'webp'],
                'video' => ['mp4', 'mov', 'avi', 'webm', 'mkv'],
                'audio' => ['mp3', 'wav', 'm4a', 'ogg', 'flac'],
            ], 'json', 'uploads'],
            ['retention_days', $defaults['retention_days'], 'int', 'uploads'],

            ['registration_open', true, 'bool', 'general'],
            ['contact_email', 'support@aimediaverifier.test', 'string', 'general'],
        ];

        foreach ($settings as [$key, $value, $type, $group]) {
            // لا نكتب فوق قيمة عدّلها المدير سابقًا
            if (! SystemSetting::where('key', $key)->exists()) {
                SystemSetting::put($key, $value, $type, $group);
            }
        }
    }
}
