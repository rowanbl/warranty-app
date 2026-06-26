<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agreement;
use App\Models\User;
use App\Notifications\EmailLoginCodeNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AgreementLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_emails_a_verified_customer(): void
    {
        Notification::fake();
        [$user, $number] = $this->customerWithAgreement(verified: true);

        $this->postJson('/api/login/agreement/request', ['agreement_number' => $number])
            ->assertOk()
            ->assertJsonPath('verified', true);

        Notification::assertSentTo($user, EmailLoginCodeNotification::class);
    }

    public function test_an_unverified_customer_is_told_to_verify(): void
    {
        Notification::fake();
        [$user, $number] = $this->customerWithAgreement(verified: false);

        $this->postJson('/api/login/agreement/request', ['agreement_number' => $number])
            ->assertStatus(409)
            ->assertJsonPath('verified', false);

        Notification::assertNotSentTo($user, EmailLoginCodeNotification::class);
    }

    public function test_an_unknown_number_is_rejected(): void
    {
        $this->postJson('/api/login/agreement/request', ['agreement_number' => '9999999999'])->assertNotFound();
    }

    public function test_the_code_signs_the_customer_in(): void
    {
        Notification::fake();
        [$user, $number] = $this->customerWithAgreement(verified: true);
        $this->postJson('/api/login/agreement/request', ['agreement_number' => $number]);

        $code = null;
        Notification::assertSentTo($user, EmailLoginCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        // Typed with the WW- and dashes, to prove it's normalised.
        $this->postJson('/api/login/agreement/verify', ['agreement_number' => 'WW-4471-228900', 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'vehicles']);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        Notification::fake();
        [, $number] = $this->customerWithAgreement(verified: true);
        $this->postJson('/api/login/agreement/request', ['agreement_number' => $number]);

        $this->postJson('/api/login/agreement/verify', ['agreement_number' => $number, 'code' => '000000'])
            ->assertJsonValidationErrorFor('code');
    }

    public function test_resend_verification_emails_an_unverified_customer(): void
    {
        Notification::fake();
        [$user, $number] = $this->customerWithAgreement(verified: false);

        $this->postJson('/api/login/agreement/resend-verification', ['agreement_number' => $number])->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function customerWithAgreement(bool $verified): array
    {
        $user = User::factory()->type(AccountType::Customer)->create([
            'email_verified_at' => $verified ? now() : null,
        ]);
        $agreement = Agreement::factory()->create([
            'user_id' => $user->id,
            'agreement_number' => '4471228900',
        ]);

        return [$user, $agreement->agreement_number];
    }
}
