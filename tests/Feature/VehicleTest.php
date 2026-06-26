<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_returns_the_saved_car(): void
    {
        $user = User::factory()->create();
        $user->vehicles()->create([
            'registration' => 'LV68KXR',
            'make' => 'BMW',
            'model' => '3 Series',
            'mileage' => 64230,
        ]);

        $this->actingAs($user)->getJson('/api/vehicle')
            ->assertOk()
            ->assertJsonPath('vehicle.registration', 'LV68KXR')
            ->assertJsonPath('vehicle.make', 'BMW')
            ->assertJsonPath('vehicle.mileage', 64230);
    }

    public function test_no_vehicle_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/vehicle')->assertNotFound();
    }

    public function test_vehicle_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/vehicle')->assertUnauthorized();
    }
}
