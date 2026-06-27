<?php

namespace App\Services\Fuel;

use App\Models\FuelStation;
use App\Services\Address\AddressService;

/**
 * Pulls the price feed into our own table and geocodes new forecourts. Run on a
 * schedule. Prices refresh for every forecourt each run; geocoding only happens
 * for ones we haven't placed yet, capped per run so the Loqate bill is bounded.
 */
class FuelIngestService
{
    public function __construct(
        private FuelFeedClient $client,
        private AddressService $addresses,
    ) {}

    /**
     * @return array{stations: int, geocoded: int, error: string|null}
     */
    public function run(): array
    {
        $stations = 0;

        foreach ($this->client->priceBatches() as $batch) {
            foreach ($batch as $raw) {
                if ($this->storePrices($raw)) {
                    $stations++;
                }
            }
        }

        return [
            'stations' => $stations,
            'geocoded' => $this->geocodePending(),
            'error' => $this->client->lastError,
        ];
    }

    /**
     * Upsert one forecourt's prices by node id. The feed lists a price per grade;
     * we store them keyed by the feed's grade codes.
     *
     * @param  array<string, mixed>  $raw
     */
    private function storePrices(array $raw): bool
    {
        $nodeId = $raw['node_id'] ?? null;

        if ($nodeId === null) {
            return false;
        }

        $prices = [];
        foreach ($raw['fuel_prices'] ?? [] as $line) {
            if (isset($line['fuel_type'], $line['price'])) {
                $prices[$line['fuel_type']] = (float) $line['price'];
            }
        }

        FuelStation::updateOrCreate(
            ['node_id' => $nodeId],
            [
                'trading_name' => $raw['trading_name'] ?? '',
                'phone' => $raw['public_phone_number'] ?? null,
                'prices' => $prices,
                'prices_updated_at' => now(),
            ],
        );

        return true;
    }

    /**
     * Geocode forecourts we haven't placed yet, from their trading name. A name
     * that can't be placed is marked failed so it isn't retried every run. Capped
     * per run, so a fresh feed clears over several runs rather than one big burst.
     */
    private function geocodePending(): int
    {
        $pending = FuelStation::whereNull('geocoded_at')
            ->where('geocode_failed', false)
            ->where('trading_name', '!=', '')
            ->limit((int) config('fuel.geocode_per_run'))
            ->get();

        $geocoded = 0;

        foreach ($pending as $station) {
            $coordinates = $this->addresses->coordinatesForAddress($station->trading_name);

            if ($coordinates === null) {
                $station->update(['geocode_failed' => true]);

                continue;
            }

            $station->update([
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'geocoded_at' => now(),
            ]);
            $geocoded++;
        }

        return $geocoded;
    }
}
