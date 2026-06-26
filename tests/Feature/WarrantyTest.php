<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    public function test_warranty_returns_the_customers_agreement(): void
    {
        $user = User::factory()->create();
        Agreement::factory()->create(['user_id' => $user->id, 'agreement_number' => '4471228900']);

        $this->actingAs($user)->getJson('/api/warranty')
            ->assertOk()
            ->assertJsonPath('agreementNumber', 'WW-4471-228900')
            ->assertJsonPath('tier', 'Gold')
            ->assertJsonStructure(['agreementNumber', 'tier', 'isActive', 'startDate', 'expiryDate', 'claimLimit', 'monthlyPrice']);
    }

    public function test_no_warranty_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/warranty')->assertNotFound();
    }

    public function test_warranty_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/warranty')->assertUnauthorized();
    }

    public function test_registering_a_customer_creates_the_warranty_agreement(): void
    {
        Notification::fake();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake']),
            'history.mot.api.gov.uk/*' => Http::response(['registration' => 'LV68KXR', 'make' => 'BMW']),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW']),
        ]);

        $dealer = User::factory()->type(AccountType::Dealer)->create();

        $this->actingAs($dealer)->postJson('/api/customers', [
            'customer' => ['name' => 'John Doe', 'email' => 'john@email.test'],
            'vehicle' => ['registration' => 'LV68KXR'],
            'warranty' => ['term_months' => 36, 'monthly' => 39.99],
            'bank' => ['account_name' => 'J Doe', 'sort_code' => '00-00-00', 'account_number' => '12345678'],
        ])->assertCreated();

        $customer = User::whereEmail('john@email.test')->firstOrFail();

        $this->assertDatabaseHas('agreements', [
            'user_id' => $customer->id,
            'monthly_price' => 39.99,
        ]);
    }
}
