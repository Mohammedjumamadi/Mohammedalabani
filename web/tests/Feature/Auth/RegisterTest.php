<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'أحمد علي',
            'email' => 'Ahmed@Example.com',
            'phone' => '+218 91 234 5678',
            'city' => 'طرابلس',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'account_type' => 'journalist',
            'organization' => 'جريدة',
            'terms' => '1',
        ], $overrides);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee(__('auth.register.heading'));
    }

    public function test_a_visitor_can_register_and_is_logged_in_with_the_user_role(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('dashboard'));
        $user = User::where('email', 'ahmed@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::USER, $user->role->slug);
        $this->assertTrue($user->isActive());
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertNotSame('Secret123', $user->password);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.register']);
    }

    public function test_role_and_status_cannot_be_injected_through_the_form(): void
    {
        $admin = Role::firstOrCreate(['slug' => Role::ADMIN], ['name_ar' => 'مدير', 'name_en' => 'Admin']);

        $this->post('/register', $this->payload(['role_id' => $admin->id, 'status' => 'suspended', 'role' => 'admin']));

        $user = User::where('email', 'ahmed@example.com')->firstOrFail();
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isActive());
    }

    public function test_weak_passwords_are_rejected(): void
    {
        foreach (['short1A', 'alllowercase1', 'NoDigitsHere', '12345678'] as $weak) {
            $this->from('/register')
                ->post('/register', $this->payload(['password' => $weak, 'password_confirmation' => $weak]))
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_terms_and_account_type_are_validated(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com']);

        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['email' => 'new@example.com', 'terms' => null]))->assertSessionHasErrors('terms');
        $this->post('/register', $this->payload(['email' => 'new@example.com', 'account_type' => 'hacker']))->assertSessionHasErrors('account_type');
        $this->post('/register', $this->payload(['email' => 'new@example.com', 'password_confirmation' => 'Different1']))->assertSessionHasErrors('password');
    }

    public function test_validation_messages_are_in_arabic(): void
    {
        $this->from('/register')->post('/register', [])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'city', 'password', 'terms'])
            ->assertSessionDoesntHaveErrors('account_type');

        $this->assertStringContainsString('مطلوب', session('errors')->first('name'));
    }

    public function test_registration_can_be_closed_by_the_admin_setting(): void
    {
        SystemSetting::put('registration_open', false);

        $this->get('/register')->assertForbidden();
        $this->post('/register', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }
}
