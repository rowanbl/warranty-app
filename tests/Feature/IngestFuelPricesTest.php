<?php

namespace Tests\Feature;

use App\Models\FuelStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestFuelPricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'fuel.client_id' => 'id',
            'fuel.client_secret' => 'secret',
            'services.loqate.key' => 'loqate-key',
        ]);
    }

    public function test_it_pulls_prices_and_geocodes_new_forecourts(): void
    {
        $this->fake([
            ['node_id' => 'node-1', 'trading_name' => 'ASDA BURNLEY', 'public_phone_number' => '+441000',
                'fuel_prices' => [
                    ['fuel_type' => 'E10', 'price' => 132.7],
                    ['fuel_type' => 'B7_STANDARD', 'price' => 138.9],
                ]],
        ]);

        $this->artisan('fuel:ingest')->assertSuccessful();

        $station = FuelStation::where('node_id', 'node-1')->firstOrFail();
        $this->assertSame('ASDA BURNLEY', $station->trading_name);
        $this->assertSame(132.7, $station->prices['E10']);
        $this->assertSame(138.9, $station->prices['B7_STANDARD']);
        $this->assertEqualsWithDelta(53.789, $station->latitude, 0.001);
        $this->assertNotNull($station->geocoded_at);
    }

    public function test_a_re_run_updates_prices_without_duplicating(): void
    {
        // One set of stubs whose price moves between runs (Http::fake stubs stack
        // first-match-wins, so re-faking wouldn't override it).
        $price = 132.7;
        Http::fake([
            'www.fuel-finder.service.gov.uk/api/v1/oauth/*' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'fake-token'],
            ]),
            'www.fuel-finder.service.gov.uk/api/v1/pfs/fuel-prices*' => function (Request $request) use (&$price) {
                return str_contains($request->url(), 'batch-number=1')
                    ? Http::response([['node_id' => 'node-1', 'trading_name' => 'ASDA BURNLEY',
                        'fuel_prices' => [['fuel_type' => 'E10', 'price' => $price]]]])
                    : Http::response([]);
            },
            'api.addressy.com/*' => Http::response(['Items' => [['Id' => 'GB|1', 'Type' => 'Address',
                'Line1' => '1 St', 'PostalCode' => 'BB11 1BD', 'Latitude' => 53.789, 'Longitude' => -2.245]]]),
        ]);

        $this->artisan('fuel:ingest')->assertSuccessful();

        $price = 129.9;
        $this->artisan('fuel:ingest')->assertSuccessful();

        $this->assertSame(1, FuelStation::where('node_id', 'node-1')->count());
        $this->assertSame(129.9, FuelStation::where('node_id', 'node-1')->firstOrFail()->prices['E10']);
    }

    public function test_geocoding_is_capped_per_run(): void
    {
        config(['fuel.geocode_per_run' => 1]);

        $this->fake([
            ['node_id' => 'n1', 'trading_name' => 'ASDA', 'fuel_prices' => [['fuel_type' => 'E10', 'price' => 1]]],
            ['node_id' => 'n2', 'trading_name' => 'BP', 'fuel_prices' => [['fuel_type' => 'E10', 'price' => 1]]],
        ]);

        $this->artisan('fuel:ingest')->assertSuccessful();

        // Both forecourts get prices, but only one is geocoded this run.
        $this->assertSame(2, FuelStation::count());
        $this->assertSame(1, FuelStation::whereNotNull('geocoded_at')->count());
    }

    public function test_feed_requests_carry_a_user_agent(): void
    {
        // The Fuel Finder edge 403s requests with no User-Agent, so every call
        // to it must send one.
        $this->fake([
            ['node_id' => 'n1', 'trading_name' => 'ASDA', 'fuel_prices' => [['fuel_type' => 'E10', 'price' => 1]]],
        ]);

        $this->artisan('fuel:ingest')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fuel-finder.service.gov.uk')
            && $request->hasHeader('User-Agent')
            && $request->header('User-Agent')[0] !== '');
    }

    public function test_missing_credentials_ingest_nothing(): void
    {
        config(['fuel.client_id' => '', 'fuel.client_secret' => '']);

        $this->artisan('fuel:ingest')->assertSuccessful();

        $this->assertSame(0, FuelStation::count());
    }

    /**
     * Fake the gov feed (token + a single price batch, then an empty page to end
     * paging) and Loqate geocoding (find, retrieve, geocode).
     *
     * @param  array<int, array<string, mixed>>  $batchOne
     */
    private function fake(array $batchOne): void
    {
        Http::fake([
            'www.fuel-finder.service.gov.uk/api/v1/oauth/*' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'fake-token'],
            ]),
            'www.fuel-finder.service.gov.uk/api/v1/pfs/fuel-prices*' => function (Request $request) use ($batchOne) {
                // Batch 1 has the data; any later page is empty, which ends paging.
                return str_contains($request->url(), 'batch-number=1')
                    ? Http::response($batchOne)
                    : Http::response([]);
            },
            'api.addressy.com/Capture/Interactive/Find/*' => Http::response([
                'Items' => [['Id' => 'GB|1', 'Type' => 'Address', 'Text' => 'x', 'Description' => 'y']],
            ]),
            'api.addressy.com/Capture/Interactive/Retrieve/*' => Http::response([
                'Items' => [['Line1' => '1 St', 'City' => 'Burnley', 'PostalCode' => 'BB11 1BD']],
            ]),
            'api.addressy.com/Geocoding/UK/*' => Http::response([
                'Items' => [['Latitude' => 53.789, 'Longitude' => -2.245]],
            ]),
        ]);
    }
}
