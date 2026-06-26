<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\BankDetail;
use App\Models\User;
use App\Notifications\HandoverCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
            'history.mot.api.gov.uk/*' => Http::response([
                'registration' => 'LV68KXR',
                'make' => 'BMW',
                'model' => '320D M SPORT',
                'fuelType' => 'Diesel',
                'primaryColour' => 'Black',
                'manufactureDate' => '2018-09-01',
                'engineSize' => '1995',
                'motTests' => [
                    ['completedDate' => '2024-08-01', 'testResult' => 'PASSED', 'expiryDate' => '2025-08-01', 'odometerValue' => '60000'],
                ],
            ]),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response([
                'registrationNumber' => 'LV68KXR',
                'taxDueDate' => '2025-03-01',
                'yearOfManufacture' => 2018,
            ]),
        ]);
    }

    public function test_a_dealer_can_set_a_customer_up(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        $response->assertCreated();
        $this->assertNotEmpty($response->json('ww_id'));
    }

    public function test_the_ww_id_is_a_plain_ten_digit_code(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        // Ten digits, no "WW" and no dashes. The apps format it for display.
        $this->assertMatchesRegularExpression('/^\d{10}$/', $response->json('ww_id'));
    }

    public function test_redeem_accepts_a_ww_id_typed_with_dashes(): void
    {
        [$wwId, $code] = $this->prepareHandover();
        $formatted = 'WW-'.substr($wwId, 0, 4).'-'.substr($wwId, 4);

        $this->postJson('/api/handovers/redeem', ['ww_id' => $formatted, 'code' => $code])->assertOk();
    }

    public function test_the_handover_creates_a_customer_account(): void
    {
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        $this->assertDatabaseHas('users', [
            'email' => 'rowan@email.test',
            'account_type' => AccountType::Customer->value,
        ]);
        $this->assertDatabaseHas('customers', ['phone' => '07700 900421']);
    }

    public function test_the_handover_saves_the_looked_up_car_with_the_dealer_mileage(): void
    {
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        // BMW from the lookup, mileage from what the dealer typed.
        $this->assertDatabaseHas('vehicles', [
            'registration' => 'LV68KXR',
            'make' => 'BMW',
            'mileage' => 64230,
        ]);
    }

    public function test_the_bank_details_are_stored_encrypted(): void
    {
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        $bank = BankDetail::firstOrFail();
        $this->assertSame('12345678', $bank->account_number);

        $raw = DB::table('bank_details')->value('account_number');
        $this->assertNotSame('12345678', $raw);
    }

    public function test_submitting_does_not_email_a_code_yet(): void
    {
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        // The code is sent when the customer enters their WW ID, not at submit.
        Notification::assertNothingSent();
    }

    public function test_entering_the_ww_id_emails_the_code(): void
    {
        Notification::fake();
        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());
        $customer = User::whereEmail('rowan@email.test')->firstOrFail();
        $wwId = DB::table('handovers')->where('customer_id', $customer->id)->value('ww_id');

        $this->postJson('/api/handovers/check', ['ww_id' => $wwId])->assertOk();

        Notification::assertSentTo($customer, HandoverCodeNotification::class);
    }

    public function test_a_customer_cannot_set_handovers_up(): void
    {
        $customer = User::factory()->type(AccountType::Customer)->create();

        $this->actingAs($customer)->postJson('/api/handovers', $this->payload())->assertForbidden();
    }

    public function test_an_unapproved_dealer_cannot_set_handovers_up(): void
    {
        $dealer = User::factory()->type(AccountType::Dealer)->unapproved()->create();

        $this->actingAs($dealer)->postJson('/api/handovers', $this->payload())->assertForbidden();
    }

    public function test_check_confirms_a_real_ww_id(): void
    {
        [$wwId] = $this->prepareHandover();

        $this->postJson('/api/handovers/check', ['ww_id' => $wwId])->assertOk();
    }

    public function test_check_rejects_an_unknown_ww_id(): void
    {
        $this->postJson('/api/handovers/check', ['ww_id' => '9999999999'])->assertNotFound();
    }

    public function test_the_agreement_number_still_works_after_the_first_login(): void
    {
        [$wwId, $code] = $this->prepareHandover();
        $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => $code]);

        // Returning login: entering the agreement number again still sends a code.
        $this->postJson('/api/handovers/check', ['ww_id' => $wwId])->assertOk();
    }

    public function test_a_customer_can_claim_their_prepared_account(): void
    {
        [$wwId, $code] = $this->prepareHandover();

        $response = $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => $code]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $response->assertJsonPath('vehicles.0.make', 'BMW');
    }

    public function test_claiming_verifies_the_customer_email(): void
    {
        [$wwId, $code] = $this->prepareHandover();

        $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => $code]);

        $this->assertNotNull(User::whereEmail('rowan@email.test')->first()->email_verified_at);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        [$wwId] = $this->prepareHandover();

        $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => '000000'])
            ->assertJsonValidationErrorFor('code');
    }

    public function test_the_agreement_number_logs_in_repeatedly(): void
    {
        [$wwId, $code] = $this->prepareHandover();

        // First login and a returning login both work, it's not one-time.
        $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => $code])->assertOk();
        $this->postJson('/api/handovers/redeem', ['ww_id' => $wwId, 'code' => $code])->assertOk();
    }

    private function dealer(): User
    {
        return User::factory()->type(AccountType::Dealer)->create();
    }

    /**
     * Submit a handover, then request the code (as the customer entering their
     * WW ID does), and return the WW ID and the emailed code.
     *
     * @return array{0: string, 1: string}
     */
    private function prepareHandover(): array
    {
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/handovers', $this->payload());

        $customer = User::whereEmail('rowan@email.test')->firstOrFail();
        $handover = DB::table('handovers')->where('customer_id', $customer->id)->first();

        // The customer entering their WW ID is what sends the code.
        $this->postJson('/api/handovers/check', ['ww_id' => $handover->ww_id]);

        $code = null;
        Notification::assertSentTo($customer, HandoverCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return [$handover->ww_id, $code];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'customer' => [
                'name' => 'Rowan Abbott',
                'email' => 'rowan@email.test',
                'phone' => '07700 900421',
                'address' => 'The Valley Works, Hapton',
            ],
            'vehicle' => [
                'registration' => 'LV68KXR',
                'mileage' => 64230,
                'insurance_renewal' => '2026-07-22',
                'last_service' => '2025-07-02',
            ],
            'cover' => [
                ['name' => 'Platinum', 'price' => 39.99, 'period' => '/month'],
            ],
            'bank' => [
                'account_name' => 'Mr R Abbott',
                'sort_code' => '00-00-00',
                'account_number' => '12345678',
            ],
            'monthly_price' => 39.99,
            'commission' => 5.00,
        ];
    }
}
