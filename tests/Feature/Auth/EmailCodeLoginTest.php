<?php

namespace Tests\Feature\Auth;

use App\Models\EmailLoginCode;
use App\Models\User;
use App\Notifications\EmailLoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_request_a_login_code(): void
    {
        Notification::fake();
        $user = User::factory()->passwordless()->create(['email' => 'rowan@email.test']);

        $response = $this->postJson('/api/login/email/request', [
            'email' => 'rowan@email.test',
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, EmailLoginCodeNotification::class);
    }

    public function test_requesting_a_code_for_an_unknown_email_still_returns_ok(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/login/email/request', [
            'email' => 'nobody@email.test',
        ]);

        // We don't reveal whether an email is registered.
        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_an_unverified_customer_is_not_sent_a_login_code(): void
    {
        Notification::fake();
        User::factory()->passwordless()->unverified()->create(['email' => 'rowan@email.test']);

        // Same generic response, but no code, since they can't sign in yet.
        $this->postJson('/api/login/email/request', ['email' => 'rowan@email.test'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_an_unverified_customer_cannot_sign_in_with_a_code(): void
    {
        // Hand-make a live code for an unverified user to prove the verify step
        // blocks them even if a code somehow exists.
        User::factory()->passwordless()->unverified()->create(['email' => 'rowan@email.test']);
        EmailLoginCode::create([
            'email' => 'rowan@email.test',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/login/email/verify', ['email' => 'rowan@email.test', 'code' => '123456'])
            ->assertForbidden();
    }

    public function test_a_valid_code_signs_the_customer_in(): void
    {
        $code = $this->requestCodeFor('rowan@email.test');

        $response = $this->postJson('/api/login/email/verify', [
            'email' => 'rowan@email.test',
            'code' => $code,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $this->requestCodeFor('rowan@email.test');

        $response = $this->postJson('/api/login/email/verify', [
            'email' => 'rowan@email.test',
            'code' => '000000',
        ]);

        $response->assertJsonValidationErrorFor('code');
    }

    public function test_a_code_only_works_once(): void
    {
        $code = $this->requestCodeFor('rowan@email.test');

        $this->postJson('/api/login/email/verify', ['email' => 'rowan@email.test', 'code' => $code]);
        $second = $this->postJson('/api/login/email/verify', ['email' => 'rowan@email.test', 'code' => $code]);

        $second->assertJsonValidationErrorFor('code');
    }

    // Requests a code for a fresh, verified customer and returns the plaintext
    // code the notification would carry, so verify has something real to use.
    private function requestCodeFor(string $email): string
    {
        Notification::fake();
        $user = User::factory()->passwordless()->create(['email' => $email]);

        $this->postJson('/api/login/email/request', ['email' => $email]);

        $code = null;
        Notification::assertSentTo($user, EmailLoginCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }
}
