<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_can_look_up_a_registration(): void
    {
        $this->fakeLookup();

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/vehicles/lookup', ['registration' => 'LV68 KXR']);

        $response->assertOk()
            ->assertJsonPath('vehicle.make', 'BMW')
            ->assertJsonPath('vehicle.year', 2018);
    }

    public function test_the_looked_up_car_is_saved_to_the_account(): void
    {
        $this->fakeLookup();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/vehicles/lookup', ['registration' => 'LV68KXR']);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $user->id,
            'registration' => 'LV68KXR',
            'make' => 'BMW',
        ]);
    }

    public function test_mileage_comes_from_the_latest_mot(): void
    {
        $this->fakeLookup();

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/vehicles/lookup', ['registration' => 'LV68KXR']);

        $response->assertJsonPath('vehicle.mileage', 64230);
    }

    public function test_an_unknown_registration_returns_not_found(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
            'history.mot.api.gov.uk/*' => Http::response([], 404),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/vehicles/lookup', ['registration' => 'XX00XXX']);

        $response->assertNotFound();
    }

    public function test_a_lookup_needs_a_signed_in_user(): void
    {
        $this->postJson('/api/vehicles/lookup', ['registration' => 'LV68KXR'])->assertUnauthorized();
    }

    public function test_the_preview_is_public_and_saves_nothing(): void
    {
        $this->fakeLookup();

        $response = $this->postJson('/api/vehicles/preview', ['registration' => 'LV68KXR']);

        $response->assertOk()->assertJsonPath('vehicle.make', 'BMW');
        $this->assertDatabaseCount('vehicles', 0);
    }

    private function fakeLookup(): void
    {
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
                    ['completedDate' => '2024-08-01', 'testResult' => 'PASSED', 'expiryDate' => '2025-08-01', 'odometerValue' => '64230', 'odometerUnit' => 'mi'],
                    ['completedDate' => '2023-07-15', 'testResult' => 'PASSED', 'expiryDate' => '2024-07-15', 'odometerValue' => '52100', 'odometerUnit' => 'mi'],
                ],
            ]),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response([
                'registrationNumber' => 'LV68KXR',
                'taxDueDate' => '2025-03-01',
                'motExpiryDate' => '2025-08-01',
                'yearOfManufacture' => 2018,
                'engineCapacity' => 1995,
                'colour' => 'BLACK',
                'fuelType' => 'DIESEL',
                'make' => 'BMW',
            ]),
        ]);
    }
}
