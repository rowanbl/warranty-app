<?php

namespace App\Http\Controllers;

use App\Services\Address\AddressService;
use App\Services\Fuel\FuelFinderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nearby fuel stations and their live prices. The app sends the user's location
 * when they share it; if they don't, it sends a postcode or address instead and
 * we place that. Either way we hand back the stations around that point.
 */
class FuelStationsController extends Controller
{
    public function index(Request $request, FuelFinderService $fuel, AddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'address' => ['nullable', 'string', 'max:255'],
            'radius' => ['nullable', 'numeric', 'between:1,25'],
            'sort' => ['nullable', 'in:nearest,cheapest'],
            'grade' => ['nullable', 'in:E5,E10,B7,SDV'],
        ]);

        $origin = $this->resolveOrigin($validated, $addresses);

        if ($origin === null) {
            return response()->json([
                'message' => 'We could not work out a location to search around.',
            ], 422);
        }

        $radius = (float) ($validated['radius'] ?? config('fuel.default_radius_miles'));
        $sort = $validated['sort'] ?? 'nearest';
        $grade = $validated['grade'] ?? FuelFinderService::DEFAULT_GRADE;

        $stations = $fuel->near($origin['latitude'], $origin['longitude'], $radius, $sort, $grade);

        $payload = [
            'origin' => $origin,
            'radius_miles' => $radius,
            'sort' => $sort,
            'grade' => $grade,
            'stations' => $stations,
        ];

        // While the app is in debug, say why an empty list is empty (feed down,
        // bad credentials, and so on). Never leaked in production.
        if (config('app.debug') && $fuel->lastError !== null) {
            $payload['diagnostic'] = $fuel->lastError;
        }

        return response()->json($payload);
    }

    /**
     * The point to search around: the shared location if we have it, otherwise
     * a geocoded postcode or address. Null when none of those place a point.
     *
     * @param  array<string, mixed>  $input
     * @return array{latitude: float, longitude: float, source: string}|null
     */
    private function resolveOrigin(array $input, AddressService $addresses): ?array
    {
        if (isset($input['lat'], $input['lng'])) {
            return ['latitude' => (float) $input['lat'], 'longitude' => (float) $input['lng'], 'source' => 'location'];
        }

        if (! empty($input['postcode'])) {
            $coordinates = $addresses->geocodePostcode($input['postcode']);

            return $coordinates ? [...$coordinates, 'source' => 'postcode'] : null;
        }

        if (! empty($input['address'])) {
            $coordinates = $addresses->coordinatesForAddress($input['address']);

            return $coordinates ? [...$coordinates, 'source' => 'address'] : null;
        }

        return null;
    }
}
