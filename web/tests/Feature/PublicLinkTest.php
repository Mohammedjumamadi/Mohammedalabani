<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** الرابط العام عبر Cloudflare Tunnel (share-aimv.ps1). */
class PublicLinkTest extends TestCase
{
    use RefreshDatabase;

    private const TUNNEL = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.trycloudflare.com', 'X-Forwarded-Port' => '443'];

    public function test_links_use_the_public_https_address_behind_the_local_tunnel(): void
    {
        $html = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(self::TUNNEL)
            ->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('https://demo.trycloudflare.com/', $html);
        $this->assertStringNotContainsString('http://demo.trycloudflare.com', $html);
    }

    public function test_forwarded_headers_from_outside_the_machine_are_ignored(): void
    {
        $html = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->withHeaders(self::TUNNEL)
            ->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('demo.trycloudflare.com', $html);
    }

    public function test_the_check_refuses_known_demo_passwords_and_debug_mode(): void
    {
        config(['app.debug' => true]);
        $this->seed(UserSeeder::class);

        $this->artisan('aimv:public-check')
            ->expectsOutputToContain(UserSeeder::ADMIN_EMAIL)
            ->assertFailed();
    }

    public function test_fix_replaces_the_published_demo_passwords(): void
    {
        config(['app.debug' => false]);
        $this->seed(UserSeeder::class);

        $this->artisan('aimv:public-check', ['--fix' => true])->assertSuccessful();

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->firstOrFail();
        $this->assertFalse(Hash::check(UserSeeder::ADMIN_PASSWORD, $admin->password));
        $this->artisan('aimv:public-check')->assertSuccessful();
    }

    public function test_fix_turns_off_debug_mode_in_the_env_file(): void
    {
        $env = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($env, "APP_NAME=x\nAPP_DEBUG=true\n");
        $this->app->loadEnvironmentFrom(basename($env));
        $this->app->useEnvironmentPath(dirname($env));
        config(['app.debug' => true]);

        $this->artisan('aimv:public-check', ['--fix' => true])->assertSuccessful();

        $this->assertStringContainsString('APP_DEBUG=false', file_get_contents($env));
        $this->assertFileExists($env.'.before-public');
        @unlink($env);
        @unlink($env.'.before-public');
    }
}
