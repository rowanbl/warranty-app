<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Garage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'account_type' => AccountType::Customer,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Give every user the profile row its account type expects, so a factory
     * user looks like a real one without each test wiring it up.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            match ($user->account_type) {
                AccountType::Customer => Customer::create(['user_id' => $user->id]),
                AccountType::Dealer => Dealer::create(['user_id' => $user->id, 'business_name' => $user->name]),
                AccountType::Garage => Garage::create(['user_id' => $user->id, 'business_name' => $user->name]),
                AccountType::Admin => null,
            };
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give the user a specific account type.
     */
    public function type(AccountType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => $type,
        ]);
    }

    /**
     * A customer with no password, who signs in with an email code.
     */
    public function passwordless(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Customer,
            'password' => null,
        ]);
    }
}
