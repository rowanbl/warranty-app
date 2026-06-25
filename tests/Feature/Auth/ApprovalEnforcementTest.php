<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApprovalEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unapproved_dealer_can_reach_me(): void
    {
        $dealer = User::factory()->type(AccountType::Dealer)->unapproved()->create();
        $token = $dealer->createToken('test')->plainTextToken;

        // /me stays open so the app can poll for approval.
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.approved', false);
    }

    public function test_an_unapproved_dealer_is_blocked_from_actions(): void
    {
        $dealer = User::factory()->type(AccountType::Dealer)->unapproved()->create();
        $token = $dealer->createToken('test')->plainTextToken;

        // The token is powerless until approval, even though it's valid.
        $this->withToken($token)
            ->postJson('/api/vehicles/lookup', ['registration' => 'AB12CDE'])
            ->assertForbidden();
    }

    public function test_an_approved_dealer_is_allowed_through(): void
    {
        $this->fakeLookup();
        $dealer = User::factory()->type(AccountType::Dealer)->create();
        $token = $dealer->createToken('test')->plainTextToken;

        // Passes the approval gate and the lookup runs (200, not a 403).
        $this->withToken($token)
            ->postJson('/api/vehicles/lookup', ['registration' => 'AB12CDE'])
            ->assertOk();
    }

    public function test_a_customer_is_never_blocked_by_approval(): void
    {
        $this->fakeLookup();
        $customer = User::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/vehicles/lookup', ['registration' => 'AB12CDE'])
            ->assertOk();
    }

    private function fakeLookup(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake']),
            'history.mot.api.gov.uk/*' => Http::response(['registration' => 'AB12CDE', 'make' => 'BMW']),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW']),
        ]);
    }
}
