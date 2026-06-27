<?php

namespace Tests\Feature;

use App\Models\FuelStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelStationsTest extends TestCase
{
    use RefreshDatabase;

    private const BURNLEY_LAT = 53.7890;

    private const BURNLEY_LNG = -2.2450;

    public function test_it_returns_stations_around_a_shared_location(): void
    {
        $this->seedStations();

        $response = $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG);

        $response->assertOk()
            ->assertJsonPath('origin.source', 'location')
            ->assertJsonStructure([
                'origin' => ['latitude', 'longitude', 'source'],
                'radius_miles',
                'sort',
                'grade',
                'stations' => [['site_id', 'brand', 'postcode', 'distance_miles', 'prices', 'cheapest']],
            ]);
    }

    public function test_a_shared_location_does_not_call_the_geocoder(): void
    {
        // A shared location is used as-is, so nothing should reach Loqate.
        $this->seedStations();
        Http::preventStrayRequests();

        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG)
            ->assertOk();
    }

    public function test_it_geocodes_a_postcode_when_no_location_is_shared(): void
    {
        Http::fake([
            'api.addressy.com/Geocoding/UK/*' => Http::response([
                'Items' => [['Latitude' => self::BURNLEY_LAT, 'Longitude' => self::BURNLEY_LNG]],
            ]),
        ]);

        $this->getJson('/api/fuel-stations?postcode=BB11 1BD')
            ->assertOk()
            ->assertJsonPath('origin.source', 'postcode');
    }

    public function test_it_falls_back_to_geocoding_a_free_text_address(): void
    {
        Http::fake([
            'api.addressy.com/Capture/Interactive/Find/*' => Http::response([
                'Items' => [['Id' => 'GB|123', 'Type' => 'Address', 'Text' => '1 High St', 'Description' => 'Burnley']],
            ]),
            'api.addressy.com/Capture/Interactive/Retrieve/*' => Http::response([
                'Items' => [['Line1' => '1 High St', 'City' => 'Burnley', 'PostalCode' => 'BB11 1BD']],
            ]),
            'api.addressy.com/Geocoding/UK/*' => Http::response([
                'Items' => [['Latitude' => self::BURNLEY_LAT, 'Longitude' => self::BURNLEY_LNG]],
            ]),
        ]);

        $this->getJson('/api/fuel-stations?address='.urlencode('1 High St, Burnley'))
            ->assertOk()
            ->assertJsonPath('origin.source', 'address');
    }

    public function test_with_nothing_ingested_the_list_is_empty_not_an_error(): void
    {
        // No stations in the table yet: a clean empty result, not a crash.
        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG)
            ->assertOk()
            ->assertJsonPath('stations', []);
    }

    public function test_in_debug_it_explains_why_the_list_is_empty(): void
    {
        config(['app.debug' => true]);

        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG)
            ->assertOk()
            ->assertJsonPath('stations', [])
            ->assertJsonStructure(['diagnostic']);
    }

    public function test_it_asks_for_a_location_when_none_is_given(): void
    {
        $this->getJson('/api/fuel-stations')->assertStatus(422);
    }

    public function test_an_unplaceable_postcode_is_a_422(): void
    {
        Http::fake([
            'api.addressy.com/Geocoding/UK/*' => Http::response(['Items' => []]),
        ]);

        $this->getJson('/api/fuel-stations?postcode=ZZ99 9ZZ')->assertStatus(422);
    }

    public function test_sort_and_grade_are_validated(): void
    {
        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG.'&sort=furthest')
            ->assertStatus(422);

        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG.'&grade=LPG')
            ->assertStatus(422);
    }

    private function seedStations(): void
    {
        $stations = [
            ['Asda', 53.7889, -2.2410, ['E5' => 142.7, 'E10' => 132.7, 'B7_STANDARD' => 138.9, 'B7_PREMIUM' => 148.9]],
            ["Sainsbury's", 53.7935, -2.2475, ['E5' => 144.9, 'E10' => 134.9, 'B7_STANDARD' => 141.7, 'B7_PREMIUM' => 151.7]],
            ['BP', 53.8021, -2.2308, ['E5' => 152.9, 'E10' => 142.9, 'B7_STANDARD' => 149.9, 'B7_PREMIUM' => 159.9]],
        ];

        foreach ($stations as [$name, $lat, $lng, $prices]) {
            FuelStation::create([
                'node_id' => strtolower($name).'-bb',
                'trading_name' => $name,
                'latitude' => $lat,
                'longitude' => $lng,
                'geocoded_at' => now(),
                'prices' => $prices,
                'prices_updated_at' => now(),
            ]);
        }
    }
}
