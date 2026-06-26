<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelStationsTest extends TestCase
{
    private const BURNLEY_LAT = 53.7890;

    private const BURNLEY_LNG = -2.2450;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fuel.client_id' => 'id', 'fuel.client_secret' => 'secret']);
    }

    public function test_it_returns_stations_around_a_shared_location(): void
    {
        $this->fakeFuelApi();

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
        // The fuel API is faked; the geocoder is not. preventStrayRequests then
        // proves a shared location never reaches Loqate.
        $this->fakeFuelApi();
        Http::preventStrayRequests();

        $this->getJson('/api/fuel-stations?lat='.self::BURNLEY_LAT.'&lng='.self::BURNLEY_LNG)
            ->assertOk();
    }

    public function test_it_geocodes_a_postcode_when_no_location_is_shared(): void
    {
        $this->fakeFuelApi();
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
        $this->fakeFuelApi();
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

    private function fakeFuelApi(): void
    {
        Http::fake([
            'api.fuelfinder.service.gov.uk/api/v1/oauth/*' => Http::response(['access_token' => 'fake-token']),
            'api.fuelfinder.service.gov.uk/api/v1/pfs/*' => Http::response(['stations' => [
                ['site_id' => 'ASDA-BB11', 'brand' => 'Asda', 'address' => 'Princess Way, Burnley', 'postcode' => 'BB11 1BD',
                    'location' => ['latitude' => 53.7889, 'longitude' => -2.2410],
                    'prices' => ['E5' => 142.7, 'E10' => 132.7, 'B7' => 138.9, 'SDV' => 148.9]],
                ['site_id' => 'BP-BB10', 'brand' => 'BP', 'address' => 'Burnley Road, Burnley', 'postcode' => 'BB10 1JZ',
                    'location' => ['latitude' => 53.8021, 'longitude' => -2.2308],
                    'prices' => ['E5' => 152.9, 'E10' => 142.9, 'B7' => 149.9, 'SDV' => 159.9]],
            ]]),
        ]);
    }
}
