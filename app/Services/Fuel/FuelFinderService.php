<?php

namespace App\Services\Fuel;

use App\Models\FuelStation;

/**
 * Finds nearby fuel stations and their prices. The data comes from our own table,
 * filled on a schedule by the Fuel Finder ingest (see FuelIngestService), since
 * the live feed has no location and pages in the thousands. We keep the stations
 * within the chosen radius, work out how far each one is, and flag the cheapest
 * for the grade we're asked about.
 *
 * The app asks in its own grade codes (E5, E10, B7, SDV). The feed uses different
 * names, so we translate, and hand prices back keyed by the app's codes so the
 * client needs no change.
 */
class FuelFinderService
{
    private const EARTH_RADIUS_MILES = 3958.8;

    // Roughly the miles in one degree of latitude, for the bounding box. Good
    // enough to pre-filter before the exact haversine check.
    private const MILES_PER_DEGREE = 69.0;

    // The standard fuel grade. We flag the cheapest and sort by price against
    // whichever grade the caller asks for, defaulting to standard unleaded.
    public const DEFAULT_GRADE = 'E10';

    // The app's grade codes mapped to the feed's. The feed splits diesel into
    // standard and premium, which the app calls B7 and SDV.
    private const GRADE_CODES = [
        'E5' => 'E5',
        'E10' => 'E10',
        'B7' => 'B7_STANDARD',
        'SDV' => 'B7_PREMIUM',
    ];

    // Why an empty result is empty, if it is. Stays null on a clean run. The
    // controller surfaces it in the response while the app is in debug.
    public ?string $lastError = null;

    /**
     * Stations within the radius, nearest or cheapest first, the cheapest one
     * for the grade flagged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function near(float $latitude, float $longitude, float $radiusMiles, string $sort = 'nearest', string $grade = self::DEFAULT_GRADE): array
    {
        $stations = $this->withinRadius($latitude, $longitude, $radiusMiles);

        if ($stations === [] && ! FuelStation::whereNotNull('latitude')->exists()) {
            $this->lastError = 'no fuel stations have been ingested yet (run php artisan fuel:ingest)';
        }

        $stations = $this->sort($stations, $sort, $grade);
        $stations = $this->flagCheapest($stations, $grade);

        return array_slice($stations, 0, (int) config('fuel.max_results'));
    }

    /**
     * The geocoded stations inside the radius, mapped to the app's shape with
     * their distance. A bounding box does the cheap pre-filter in the database,
     * then the exact haversine trims the corners off that box.
     *
     * @return array<int, array<string, mixed>>
     */
    private function withinRadius(float $latitude, float $longitude, float $radiusMiles): array
    {
        $latDelta = $radiusMiles / self::MILES_PER_DEGREE;
        // Longitude lines bunch up towards the poles, so widen by latitude.
        $lngDelta = $radiusMiles / max(0.1, self::MILES_PER_DEGREE * cos(deg2rad($latitude)));

        $candidates = FuelStation::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->get();

        $near = [];

        foreach ($candidates as $station) {
            $distance = $this->distanceMiles($latitude, $longitude, $station->latitude, $station->longitude);

            if ($distance > $radiusMiles) {
                continue;
            }

            $near[] = [
                'site_id' => $station->node_id,
                'brand' => $station->trading_name,
                'address' => $station->trading_name,
                'postcode' => $station->postcode,
                'distance_miles' => round($distance, 1),
                'prices' => $this->appPrices($station->prices ?? []),
                'cheapest' => false,
            ];
        }

        return $near;
    }

    /**
     * Translate stored feed prices to the app's grade codes, dropping any grade
     * the forecourt doesn't sell.
     *
     * @param  array<string, mixed>  $feedPrices
     * @return array<string, float>
     */
    private function appPrices(array $feedPrices): array
    {
        $prices = [];

        foreach (self::GRADE_CODES as $appGrade => $feedCode) {
            if (isset($feedPrices[$feedCode])) {
                $prices[$appGrade] = (float) $feedPrices[$feedCode];
            }
        }

        return $prices;
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
