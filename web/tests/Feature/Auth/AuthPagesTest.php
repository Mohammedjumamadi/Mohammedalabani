<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** تصميم صفحتي الدخول والتسجيل: الحقول، CSRF، مبدّل اللغة، ورسائل التحقق باللغتين. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_pages_have_the_required_fields_csrf_and_language_switch(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)->assertSee('name="password"', false)->assertSee('name="remember"', false)
            ->assertSee(route('register'))
            ->assertSee(route('locale.switch', 'en'))->assertSee('lang-switch', false);

        $this->get('/register')->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="name"', false)->assertSee('name="email"', false)
            ->assertSee('name="phone"', false)->assertSee('name="city"', false)
            ->assertSee('name="password"', false)->assertSee('name="password_confirmation"', false)
            ->assertSee(route('login'))
            ->assertSee(route('locale.switch', 'en'));
    }

    public function test_auth_pages_share_the_home_navigation_and_offer_password_reveal(): void
    {
        foreach (['/', '/login', '/register'] as $url) {
            $this->get($url)->assertOk()->assertSee('site-nav', false)->assertSee('photo_2026-09-24_13-06-22.jpg', false);
        }
        $this->get('/login')->assertSee('data-reveal="password"', false)->assertSee(__('landing.auth.show_password'));
        $this->get('/register')->assertSee('data-reveal="password_confirmation"', false);
    }

    public function test_registration_saves_phone_and_city(): void
    {
        $this->post('/register', [
            'name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '+218 91 000 1111', 'city' => 'مصراتة',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com', 'phone' => '+218 91 000 1111', 'city' => 'مصراتة', 'account_type' => 'general']);
    }

    public function test_the_wizard_reopens_on_the_step_that_has_errors(): void
    {
        $this->from('/register')->post('/register', ['name' => 'Ali', 'email' => 'ali@example.com', 'phone' => 'abc']);
        $this->get('/register')->assertSee('step: 2', false);
    }

    public function test_the_home_navigation_has_the_language_switch(): void
    {
        $this->get('/')->assertOk()->assertSee(route('locale.switch', 'en'))->assertSee(route('locale.switch', 'ar'));
    }

    public function test_validation_messages_follow_the_selected_language(): void
    {
        $this->from('/register')->post('/register', ['email' => 'bad', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertStringContainsString('الاسم', session('errors')->first('name'));

        $this->get('/lang/en');
        $this->from('/register')->post('/register', ['email' => 'bad'])->assertSessionHasErrors('name');
        $this->assertStringContainsString('name', strtolower(session('errors')->first('name')));
    }

    public function test_wrong_credentials_show_a_translated_error(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => __('auth.failed', [], 'ar')]);

        $this->get('/lang/en');
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => __('auth.failed', [], 'en')]);
    }

    public function test_the_language_chosen_before_signing_in_is_kept_after_login(): void
    {
        $user = User::factory()->create(['locale' => 'ar', 'password' => 'Secret123!']);

        $this->get('/lang/en');
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123!'])->assertRedirect(route('dashboard'));

        $this->assertSame('en', $user->fresh()->locale);
        $this->get('/dashboard')->assertSee('lang="en"', false);
    }
}
