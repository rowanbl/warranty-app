<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ww:demo-accounts')]
#[Description('Create demo accounts (Warranty Wise staff, dealer, garage, customer) for testing. All verified, password "password". Safe to re-run.')]
class SeedDemoAccounts extends Command
{
    private const PASSWORD = 'password';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const ACCOUNTS = [
        ['type' => AccountType::Admin, 'name' => 'Warranty Wise Staff', 'email' => 'admin@warrantywise.test'],
        ['type' => AccountType::Dealer, 'name' => 'Demo Motors', 'email' => 'dealer@warrantywise.test', 'business_name' => 'Demo Motors', 'phone' => '01234 567890', 'address' => '1 High Street, London'],
        ['type' => AccountType::Garage, 'name' => 'Demo Garage', 'email' => 'garage@warrantywise.test', 'business_name' => 'Demo Garage', 'phone' => '01234 567891', 'address' => '2 High Street, London'],
        ['type' => AccountType::Customer, 'name' => 'John Doe', 'email' => 'customer@warrantywise.test', 'phone' => '07700 900000', 'address' => '3 High Street, London'],
    ];

    public function handle(): int
    {
        foreach (self::ACCOUNTS as $account) {
            $this->createAccount($account);
        }

        $this->newLine();
        $this->table(
            ['Type', 'Email', 'Password'],
            array_map(fn (array $a) => [$a['type']->value, $a['email'], self::PASSWORD], self::ACCOUNTS),
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function createAccount(array $account): void
    {
        $user = User::updateOrCreate(
            ['email' => $account['email']],
            [
                'name' => $account['name'],
                'account_type' => $account['type'],
                'password' => self::PASSWORD,
            ],
        );

        // email_verified_at isn't mass assignable, so verify explicitly. Demo
        // accounts skip the magic link so they can sign in straight away.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $this->createProfile($user, $account);
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function createProfile(User $user, array $account): void
    {
        $relation = match ($user->account_type) {
            AccountType::Customer => $user->customer(),
            AccountType::Dealer => $user->dealer(),
            AccountType::Garage => $user->garage(),
            AccountType::Admin => null,
        };

        if ($relation === null) {
            return;
        }

        $attributes = [
            'phone' => $account['phone'] ?? null,
            'address' => $account['address'] ?? null,
        ];

        if (isset($account['business_name'])) {
            $attributes['business_name'] = $account['business_name'];
            // Demo dealers/garages are pre-approved so they're ready to test.
            $attributes['approved_at'] = now();
        }

        // Empty match array scopes to this user, so re-running updates in place.
        $relation->updateOrCreate([], $attributes);
    }
}
