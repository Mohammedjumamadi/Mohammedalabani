<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_to_registered_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', __('auth.forgot.sent'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_emails_get_the_same_response_to_prevent_account_enumeration(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', __('auth.forgot.sent'))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('BrandNew123', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.password_reset']);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'OldPass123']);

        $this->from('/forgot-password')->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));
    }

    public function test_the_new_password_must_satisfy_the_password_policy(): void
    {
        $this->from('/forgot-password')->post('/reset-password', [
            'token' => 'x',
            'email' => 'a@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');
    }
}
