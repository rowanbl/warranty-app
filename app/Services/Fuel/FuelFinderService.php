<?php

namespace App\Services\Fuel;

use Illuminate\Support\Facades\Http;

/**
 * Finds nearby fuel stations and their live prices from the government Fuel
 * Finder open-data API (Motor Fuel Price (Open Data) Regulations 2025).
 *
 * Access is OAuth client-credentials: we swap the client id + secret for a
 * short-lived Bearer token, then pull the feed of every registered forecourt.
 * We keep the stations within the chosen radius, work out how far each one is,
 * and flag the cheapest for the grade we're asked about.
 */
class FuelFinderService
{
    private const EARTH_RADIUS_MILES = 3958.8;

    // The standard fuel grade. We flag the cheapest and sort by price against
    // whichever grade the caller asks for, defaulting to standard unleaded.
    public const DEFAULT_GRADE = 'E10';

    /**
     * Stations within the radius, nearest or cheapest first, the cheapest one
     * for the grade flagged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function near(float $latitude, float $longitude, float $radiusMiles, string $sort = 'nearest', string $grade = self::DEFAULT_GRADE): array
    {
        $stations = $this->withinRadius($latitude, $longitude, $radiusMiles);

        $stations = $this->sort($stations, $sort, $grade);
        $stations = $this->flagCheapest($stations, $grade);

        return array_slice($stations, 0, config('fuel.max_results'));
    }

    /**
     * Every station in the feed, mapped to the app's shape with its distance
     * from the given point, filtered to those inside the radius.
     *
     * @return array<int, array<string, mixed>>
     */
    private function withinRadius(float $latitude, float $longitude, float $radiusMiles): array
    {
        $near = [];

        foreach ($this->feed() as $station) {
            $location = $station['location'] ?? [];
            $stationLat = $location['latitude'] ?? null;
            $stationLng = $location['longitude'] ?? null;

            if ($stationLat === null || $stationLng === null) {
                continue;
            }

            $distance = $this->distanceMiles($latitude, $longitude, (float) $stationLat, (float) $stationLng);

            if ($distance > $radiusMiles) {
                continue;
            }

            $near[] = [
                'site_id' => $station['site_id'] ?? null,
                'brand' => $station['brand'] ?? null,
                'address' => $station['address'] ?? null,
                'postcode' => $station['postcode'] ?? null,
                'latitude' => (float) $stationLat,
                'longitude' => (float) $stationLng,
                'distance_miles' => round($distance, 1),
                'prices' => $station['prices'] ?? [],
                'cheapest' => false,
            ];
        }

        return $near;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stations
     * @return array<int, array<string, mixed>>
     */
    private function sort(array $stations, string $sort, string $grade): array
    {
        if ($sort === 'cheapest') {
            // Cheapest first for the chosen grade. Stations that don't sell that
            // grade have no price to compare, so they sink to the bottom.
            usort($stations, function (array $a, array $b) use ($grade) {
                return $this->priceFor($a, $grade) <=> $this->priceFor($b, $grade);
            });

            return $stations;
        }

        usort($stations, fn (array $a, array $b) => $a['distance_miles'] <=> $b['distance_miles']);

        return $stations;
    }

    /**
     * Mark the single cheapest station for the grade. Ties go to the first,
     * which after sorting is the nearest or already the cheapest.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @return array<int, array<string, mixed>>
     */
    private function flagCheapest(array $stations, string $grade): array
    {
        $cheapestIndex = null;
        $cheapestPrice = null;

        foreach ($stations as $index => $station) {
            $price = $station['prices'][$grade] ?? null;

            if ($price === null) {
                continue;
            }

            if ($cheapestPrice === null || $price < $cheapestPrice) {
                $cheapestPrice = $price;
                $cheapestIndex = $index;
            }
        }

        if ($cheapestIndex !== null) {
            $stations[$cheapestIndex]['cheapest'] = true;
        }

        return $stations;
    }

    /**
     * A station's price for a grade, or a large number when it doesn't sell it,
     * so missing grades sort last rather than first.
     *
     * @param  array<string, mixed>  $station
     */
    private function priceFor(array $station, string $grade): float
    {
        return (float) ($station['prices'][$grade] ?? PHP_FLOAT_MAX);
    }

    /**
     * The raw station list from the live feed, or an empty list when the API is
     * unreachable or unconfigured (the app then just shows no stations rather
     * than stale data).
     *
     * @return array<int, array<string, mixed>>
     */
    private function feed(): array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return [];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(config('fuel.feed_url'));

        if (! $response->successful()) {
            return [];
        }

        return $response->json('stations') ?? [];
    }

    /**
     * Swap the client id + secret for a short-lived Bearer token. Null when the
     * credentials are missing or the token call fails.
     */
    private function accessToken(): ?string
    {
        $clientId = config('fuel.client_id');
        $clientSecret = config('fuel.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $response = Http::asForm()->post(config('fuel.token_url'), [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * Great-circle distance between two points in miles (haversine).
     */
    private function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * asin(sqrt($a));
    }
}
