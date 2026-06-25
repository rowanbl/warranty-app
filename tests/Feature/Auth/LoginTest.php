<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_logs_in_and_gets_a_token(): void
    {
        User::factory()->type(AccountType::Dealer)->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_the_login_response_says_which_account_type_signed_in(): void
    {
        User::factory()->type(AccountType::Dealer)->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response->assertJsonPath('user.account_type', 'dealer');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'wrong-password',
        ]);

        $response->assertJsonValidationErrorFor('email');
    }

    public function test_an_unapproved_dealer_signs_in_but_is_flagged_unapproved(): void
    {
        User::factory()->type(AccountType::Dealer)->unapproved()->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        // They sign in (so the app remembers them) but the app holds them on the
        // awaiting-approval screen until approved is true.
        $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('user.approved', false);
    }

    public function test_an_approved_dealer_is_flagged_approved(): void
    {
        User::factory()->type(AccountType::Dealer)->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('user.approved', true);
    }

    public function test_customers_are_always_approved(): void
    {
        User::factory()->create(['email' => 'rowan@email.test', 'password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => 'rowan@email.test',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('user.approved', true);
    }

    public function test_an_unverified_user_cannot_log_in(): void
    {
        User::factory()->unverified()->create([
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
        ]);

        $response->assertForbidden();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        User::factory()->create(['email' => 'sales@acme.test']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => 'sales@acme.test',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'sales@acme.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
