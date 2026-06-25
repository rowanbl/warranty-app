<?php

namespace Database\Factories;

use App\Enums\WarrantyTier;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => null,
            'agreement_number' => 'WW-'.fake()->unique()->numerify('####-#####'),
            'tier' => WarrantyTier::Gold,
            'status' => 'active',
            'start_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'claim_limit' => 1500,
            'monthly_price' => 24.99,
        ];
    }
}
