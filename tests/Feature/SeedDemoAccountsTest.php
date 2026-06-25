<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedDemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_account_of_each_type(): void
    {
        $this->artisan('ww:demo-accounts')->assertSuccessful();

        $this->assertSame(AccountType::Admin, User::whereEmail('admin@warrantywise.test')->first()->account_type);
        $this->assertSame(AccountType::Dealer, User::whereEmail('dealer@warrantywise.test')->first()->account_type);
        $this->assertSame(AccountType::Garage, User::whereEmail('garage@warrantywise.test')->first()->account_type);
        $this->assertSame(AccountType::Customer, User::whereEmail('customer@warrantywise.test')->first()->account_type);
    }

    public function test_the_dealer_gets_a_profile_and_can_sign_in(): void
    {
        $this->artisan('ww:demo-accounts');

        $dealer = User::whereEmail('dealer@warrantywise.test')->first();

        $this->assertNotNull($dealer->email_verified_at);
        $this->assertSame('Demo Motors', $dealer->dealer->business_name);
        $this->assertTrue(Hash::check('password', $dealer->password));
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->artisan('ww:demo-accounts');
        $this->artisan('ww:demo-accounts');

        $this->assertSame(1, User::whereEmail('dealer@warrantywise.test')->count());
        $this->assertSame(1, User::whereEmail('dealer@warrantywise.test')->first()->dealer()->count());
    }
}
