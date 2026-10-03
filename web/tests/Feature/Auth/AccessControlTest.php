<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admins_can_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_dashboard_and_pages_render_for_users_with_their_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($user->name);
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->get('/settings')->assertOk();
    }

    public function test_profile_update_changes_only_allowed_fields(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->actingAs($user)->put('/profile', [
            'name' => 'اسم جديد',
            'phone' => '+966 50 123 4567',
            'organization' => 'جهة',
            'account_type' => 'student',
            'email' => 'hacker@example.com',
            'role_id' => 999,
            'status' => 'suspended',
        ])->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertSame('اسم جديد', $fresh->name);
        $this->assertSame('student', $fresh->account_type);
        $this->assertSame('keep@example.com', $fresh->email);
        $this->assertSame($user->role_id, $fresh->role_id);
        $this->assertTrue($fresh->isActive());
    }

    public function test_phone_rejects_letters_and_scripts(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/profile', ['name' => 'X', 'phone' => '<script>', 'account_type' => 'student'])
            ->assertSessionHasErrors('phone');
    }

    public function test_changing_the_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPass123']);

        $this->actingAs($user)->from('/settings')->put('/settings/password', [
            'current_password' => 'not-the-password',
            'password' => 'NewPass456',
            'password_confirmation' => 'NewPass456',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass456',
            'password_confirmation' => 'NewPass456',
        ])->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.password_changed']);
    }

    public function test_the_language_switch_is_saved_to_the_user_and_applied(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)->get('/lang/en')->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user->fresh())->get('/dashboard')->assertSee('dir="ltr"', false)->assertSee('lang="en"', false);
    }

    public function test_an_unsupported_language_is_a_404(): void
    {
        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_guests_can_switch_language_through_the_session(): void
    {
        $this->get('/lang/en');
        $this->get('/login')->assertSee('dir="ltr"', false);
    }

    public function test_unknown_pages_show_the_safe_arabic_404_page(): void
    {
        $this->get('/this-does-not-exist')
            ->assertNotFound()
            ->assertSee(__('errors.404.title'))
            ->assertDontSee('vendor/laravel')
            ->assertDontSee('Stack trace');
    }
}
