<?php

namespace Tests\Unit;

use App\Services\Fuel\FuelFinderService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The service is driven by the live Fuel Finder API, faked here as a cluster of
 * forecourts around Burnley. We search from the town centre.
 */
class FuelFinderServiceTest extends TestCase
{
    private const BURNLEY_LAT = 53.7890;

    private const BURNLEY_LNG = -2.2450;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeFuelApi();
    }

    public function test_it_returns_stations_with_a_distance_and_all_grades(): void
    {
        $stations = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5);

        $this->assertNotEmpty($stations);

        $first = $stations[0];
        $this->assertArrayHasKey('distance_miles', $first);
        $this->assertArrayHasKey('prices', $first);
        $this->assertArrayHasKey('E10', $first['prices']);
        $this->assertArrayHasKey('B7', $first['prices']);
    }

    public function test_a_tighter_radius_drops_the_far_stations(): void
    {
        $wide = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5);
        $tight = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 1);

        $this->assertLessThan(count($wide), count($tight));

        foreach ($tight as $station) {
            $this->assertLessThanOrEqual(1, $station['distance_miles']);
        }
    }

    public function test_nearest_sort_orders_by_distance(): void
    {
        $stations = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5, 'nearest');

        $distances = array_column($stations, 'distance_miles');
        $sorted = $distances;
        sort($sorted);

        $this->assertSame($sorted, $distances);
    }

    public function test_cheapest_sort_orders_by_the_grade_price(): void
    {
        $stations = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5, 'cheapest', 'E10');

        $prices = array_map(fn ($s) => $s['prices']['E10'], $stations);
        $sorted = $prices;
        sort($sorted);

        $this->assertSame($sorted, $prices);
    }

    public function test_the_single_cheapest_station_for_the_grade_is_flagged(): void
    {
        $stations = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5, 'nearest', 'E10');

        $flagged = array_filter($stations, fn ($s) => $s['cheapest'] === true);
        $this->assertCount(1, $flagged);

        $cheapestPrice = min(array_map(fn ($s) => $s['prices']['E10'], $stations));
        $this->assertSame($cheapestPrice, reset($flagged)['prices']['E10']);
    }

    public function test_it_returns_nothing_when_the_credentials_are_missing(): void
    {
        config(['fuel.client_id' => '', 'fuel.client_secret' => '']);

        $this->assertSame([], $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5));
    }

    private function finder(): FuelFinderService
    {
        return new FuelFinderService;
    }

    private function fakeFuelApi(): void
    {
        config(['fuel.client_id' => 'id', 'fuel.client_secret' => 'secret']);

        Http::fake([
            'api.fuelfinder.service.gov.uk/api/v1/oauth/*' => Http::response(['access_token' => 'fake-token']),
            'api.fuelfinder.service.gov.uk/api/v1/pfs/*' => Http::response(['stations' => self::STATIONS]),
        ]);
    }

    // A real cluster of Burnley-area forecourts in the scheme feed shape.
    private const STATIONS = [
        ['site_id' => 'ASDA-BB11', 'brand' => 'Asda', 'address' => 'Princess Way, Burnley', 'postcode' => 'BB11 1BD',
            'location' => ['latitude' => 53.7889, 'longitude' => -2.2410],
            'prices' => ['E5' => 142.7, 'E10' => 132.7, 'B7' => 138.9, 'SDV' => 148.9]],
        ['site_id' => 'SAINS-BB11', 'brand' => "Sainsbury's", 'address' => 'Centenary Way, Burnley', 'postcode' => 'BB11 1BS',
            'location' => ['latitude' => 53.7935, 'longitude' => -2.2475],
            'prices' => ['E5' => 144.9, 'E10' => 134.9, 'B7' => 141.7, 'SDV' => 151.7]],
        ['site_id' => 'BP-BB10', 'brand' => 'BP', 'address' => 'Burnley Road, Burnley', 'postcode' => 'BB10 1JZ',
            'location' => ['latitude' => 53.8021, 'longitude' => -2.2308],
            'prices' => ['E5' => 152.9, 'E10' => 142.9, 'B7' => 149.9, 'SDV' => 159.9]],
        ['site_id' => 'TESCO-BB12', 'brand' => 'Tesco', 'address' => 'Barden Lane, Burnley', 'postcode' => 'BB10 1JR',
            'location' => ['latitude' => 53.8104, 'longitude' => -2.2521],
            'prices' => ['E5' => 143.9, 'E10' => 133.9, 'B7' => 139.7, 'SDV' => 149.7]],
        ['site_id' => 'SHELL-BB11', 'brand' => 'Shell', 'address' => 'Accrington Road, Burnley', 'postcode' => 'BB11 5EL',
            'location' => ['latitude' => 53.7782, 'longitude' => -2.2689],
            'prices' => ['E5' => 151.9, 'E10' => 141.9, 'B7' => 148.9, 'SDV' => 158.9]],
    ];
}
