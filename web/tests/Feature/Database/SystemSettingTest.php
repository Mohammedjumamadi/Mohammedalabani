<?php

namespace Tests\Feature\Database;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_values_round_trip_with_their_types(): void
    {
        SystemSetting::put('a_int', 42);
        SystemSetting::put('a_float', 1.5);
        SystemSetting::put('a_bool', false);
        SystemSetting::put('a_json', ['x' => ['y', 'z']]);
        SystemSetting::put('a_string', 'مرحبا');

        $this->assertSame(42, SystemSetting::get('a_int'));
        $this->assertSame(1.5, SystemSetting::get('a_float'));
        $this->assertFalse(SystemSetting::get('a_bool'));
        $this->assertSame(['x' => ['y', 'z']], SystemSetting::get('a_json'));
        $this->assertSame('مرحبا', SystemSetting::get('a_string'));
    }

    public function test_missing_setting_falls_back_to_config_default(): void
    {
        $this->assertSame(70, SystemSetting::get('threshold_fake'));
        $this->assertSame(30, SystemSetting::get('threshold_real'));
        $this->assertSame('fallback', SystemSetting::get('does_not_exist', 'fallback'));
    }

    public function test_updates_are_visible_immediately_despite_caching(): void
    {
        $this->assertSame(70, SystemSetting::get('threshold_fake'));

        SystemSetting::put('threshold_fake', 80);
        $this->assertSame(80, SystemSetting::get('threshold_fake'));

        SystemSetting::put('threshold_fake', 60);
        $this->assertSame(60, SystemSetting::get('threshold_fake'));
    }
}
