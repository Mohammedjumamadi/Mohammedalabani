<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_split_layout_in_arabic_rtl(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('site-auth', false)
            ->assertSee('auth-card', false)
            ->assertSee(__('auth.login.heading'));
    }

    public function test_user_can_log_in_and_is_sent_to_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_email_is_case_insensitive_at_login(): void
    {
        $user = User::factory()->create(['email' => 'mixed@example.com', 'password' => 'Secret123']);

        $this->post('/login', ['email' => '  Mixed@Example.COM ', 'password' => 'Secret123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_is_sent_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'Secret123']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'Secret123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_password_is_rejected_with_a_generic_message_and_logged(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $response->assertRedirect('/login')->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login_failed']);
    }

    public function test_unknown_email_gets_the_same_message_as_a_wrong_password(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever1A'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_suspended_accounts_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create(['password' => 'Secret123']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertSessionHasErrors(['email' => __('auth.suspended')]);

        $this->assertGuest();
    }

    public function test_a_session_is_ended_when_the_account_gets_suspended(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $payload = ['email' => 'target@example.com', 'password' => 'bad-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $payload)->assertStatus(302);
        }

        $this->post('/login', $payload)->assertStatus(429);
    }

    public function test_logout_ends_the_session_and_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(1, ActivityLog::where('action', 'auth.logout')->count());
    }

    public function test_logout_requires_post_and_csrf_protection_is_active(): void
    {
        $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
    }

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_logged_in_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
        $this->actingAs(User::factory()->admin()->create())->get('/login')->assertRedirect(route('admin.dashboard'));
    }
}
