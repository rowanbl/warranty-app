<?php

namespace Tests\Unit;

use App\Models\FuelStation;
use App\Services\Fuel\FuelFinderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The service reads the ingested `fuel_stations` table. We seed a cluster of
 * Burnley-area forecourts (prices in the feed's grade codes) and search from the
 * town centre.
 */
class FuelFinderServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BURNLEY_LAT = 53.7890;

    private const BURNLEY_LNG = -2.2450;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStations();
    }

    public function test_it_returns_stations_with_a_distance_and_all_grades(): void
    {
        $stations = $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5);

        $this->assertNotEmpty($stations);

        $first = $stations[0];
        $this->assertArrayHasKey('distance_miles', $first);
        $this->assertArrayHasKey('prices', $first);
        // Prices come back in the app's grade codes, not the feed's.
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

    public function test_an_empty_table_returns_nothing(): void
    {
        FuelStation::query()->delete();

        $this->assertSame([], $this->finder()->near(self::BURNLEY_LAT, self::BURNLEY_LNG, 5));
    }

    private function finder(): FuelFinderService
    {
        return new FuelFinderService;
    }

    private function seedStations(): void
    {
        $stations = [
            ['Asda', 53.7889, -2.2410, ['E5' => 142.7, 'E10' => 132.7, 'B7_STANDARD' => 138.9, 'B7_PREMIUM' => 148.9]],
            ["Sainsbury's", 53.7935, -2.2475, ['E5' => 144.9, 'E10' => 134.9, 'B7_STANDARD' => 141.7, 'B7_PREMIUM' => 151.7]],
            ['BP', 53.8021, -2.2308, ['E5' => 152.9, 'E10' => 142.9, 'B7_STANDARD' => 149.9, 'B7_PREMIUM' => 159.9]],
            ['Tesco', 53.8104, -2.2521, ['E5' => 143.9, 'E10' => 133.9, 'B7_STANDARD' => 139.7, 'B7_PREMIUM' => 149.7]],
            ['Shell', 53.7782, -2.2689, ['E5' => 151.9, 'E10' => 141.9, 'B7_STANDARD' => 148.9, 'B7_PREMIUM' => 158.9]],
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
