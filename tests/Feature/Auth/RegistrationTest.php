<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dealer_can_register_with_a_password(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Acme Motors',
            'email' => 'sales@acme.test',
            'account_type' => 'dealer',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'sales@acme.test',
            'account_type' => 'dealer',
        ]);
    }

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Acme Motors',
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        Notification::assertSentTo(User::whereEmail('sales@acme.test')->first(), VerifyEmail::class);
    }

    public function test_registration_does_not_return_a_token_before_the_email_is_verified(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Acme Motors',
            'email' => 'sales@acme.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertJsonMissingPath('token');
    }

    public function test_registration_defaults_to_a_customer_account(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Rowan Abbott',
            'email' => 'rowan@email.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertSame(AccountType::Customer, User::whereEmail('rowan@email.test')->first()->account_type);
    }

    public function test_a_user_cannot_register_themselves_as_an_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@email.test',
            'account_type' => 'admin',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertJsonValidationErrorFor('account_type');
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@email.test']);

        $response = $this->postJson('/api/register', [
            'name' => 'Someone',
            'email' => 'taken@email.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertJsonValidationErrorFor('email');
    }
}
