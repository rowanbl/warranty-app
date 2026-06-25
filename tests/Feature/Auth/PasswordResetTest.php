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

    public function test_a_reset_link_is_sent_for_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'sales@acme.test']);

        $this->postJson('/api/forgot-password', ['email' => 'sales@acme.test'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_still_returns_ok(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nobody@email.test'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'sales@acme.test']);
        $this->postJson('/api/forgot-password', ['email' => 'sales@acme.test']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'sales@acme.test',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }
}
