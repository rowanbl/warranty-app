<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\BankDetail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dealer_registers_a_customers_whole_account(): void
    {
        $this->fakeLookup();
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/customers', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['agreement_number', 'customer' => ['id', 'email']]);

        $customer = User::whereEmail('rowan@email.test')->firstOrFail();
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertDatabaseHas('vehicles', ['user_id' => $customer->id, 'registration' => 'LV68KXR']);
        $this->assertDatabaseHas('agreements', ['user_id' => $customer->id]);
        $this->assertDatabaseHas('bank_details', ['user_id' => $customer->id]);
    }

    public function test_the_customer_starts_unverified_and_is_emailed_a_link(): void
    {
        $this->fakeLookup();
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/customers', $this->payload());

        $customer = User::whereEmail('rowan@email.test')->firstOrFail();
        $this->assertFalse($customer->hasVerifiedEmail());
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_bank_details_are_stored_encrypted(): void
    {
        $this->fakeLookup();
        Notification::fake();

        $this->actingAs($this->dealer())->postJson('/api/customers', $this->payload());

        $bank = BankDetail::firstOrFail();
        $this->assertSame('12345678', $bank->account_number);
        $this->assertNotSame('12345678', DB::table('bank_details')->value('account_number'));
    }

    public function test_a_customer_cannot_register_customers(): void
    {
        $customer = User::factory()->type(AccountType::Customer)->create();
        $this->actingAs($customer)->postJson('/api/customers', $this->payload())->assertForbidden();
    }

    public function test_an_unapproved_dealer_cannot_register_customers(): void
    {
        $dealer = User::factory()->type(AccountType::Dealer)->unapproved()->create();
        $this->actingAs($dealer)->postJson('/api/customers', $this->payload())->assertForbidden();
    }

    public function test_the_warranty_is_required(): void
    {
        $payload = $this->payload();
        unset($payload['warranty']);

        $this->actingAs($this->dealer())->postJson('/api/customers', $payload)
            ->assertJsonValidationErrorFor('warranty');
    }

    private function dealer(): User
    {
        return User::factory()->type(AccountType::Dealer)->create();
    }

    private function fakeLookup(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake']),
            'history.mot.api.gov.uk/*' => Http::response(['registration' => 'LV68KXR', 'make' => 'BMW']),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW', 'yearOfManufacture' => 2020]),
        ]);
    }

    public function test_a_car_outside_the_plans_is_refused(): void
    {
        // 2005 car, way over age + mileage → no plan, so registration is rejected.
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake']),
            'history.mot.api.gov.uk/*' => Http::response(['registration' => 'LV68KXR', 'make' => 'BMW']),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW', 'yearOfManufacture' => 2005]),
        ]);
        Notification::fake();

        $payload = $this->payload();
        $payload['vehicle']['mileage'] = 200000;

        $this->actingAs($this->dealer())->postJson('/api/customers', $payload)
            ->assertJsonValidationErrorFor('vehicle');

        $this->assertDatabaseCount('agreements', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'customer' => ['name' => 'Rowan Abbott', 'email' => 'rowan@email.test', 'phone' => '07000 000000', 'address' => '1 Test St'],
            'vehicle' => ['registration' => 'LV68KXR', 'mileage' => 64230],
            'warranty' => ['term_months' => 36, 'monthly' => 39.99],
            'bank' => ['account_name' => 'R Abbott', 'sort_code' => '00-00-00', 'account_number' => '12345678'],
        ];
    }
}
