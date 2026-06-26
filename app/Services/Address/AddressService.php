<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\Http;

/**
 * Address autocomplete and UK geocoding via Loqate (Addressy).
 *
 * Three calls cover everything we need:
 *  - search() drives the type-ahead. It returns suggestions; some are a final
 *    address, some are a container (a street or postcode) the user drills into
 *    by searching again with its id.
 *  - retrieve() turns a chosen address id into the full address plus its
 *    coordinates, so a picked address is ready to use.
 *  - coordinatesForAddress() is the fallback for the fuel finder: when a user
 *    won't share their location we geocode the address already on their
 *    account.
 *
 * We talk to Loqate's REST endpoints directly, the same way the vehicle lookup
 * talks to the DVSA, so there's no SDK to carry and the calls fake cleanly in
 * tests.
 */
class AddressService
{
    private const FIND_URL = 'https://api.addressy.com/Capture/Interactive/Find/v1.10/json3.ws';

    private const RETRIEVE_URL = 'https://api.addressy.com/Capture/Interactive/Retrieve/v1.20/json3.ws';

    private const GEOCODE_URL = 'https://api.addressy.com/Geocoding/UK/Geocode/v2.10/json3.ws';

    /**
     * Suggestions for what the user has typed. Pass a container id to drill into
     * a street or postcode they picked.
     *
     * @return array<int, array{id: string, type: string, text: string, description: string}>
     */
    public function search(string $text, ?string $container = null): array
    {
        $response = Http::get(self::FIND_URL, [
            'Key' => $this->key(),
            'Text' => $text,
            'Container' => $container ?? '',
            'Countries' => 'GB',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $suggestions = [];

        foreach ($response->json('Items') ?? [] as $item) {
            // Loqate reports failures as an item with an Error field. Skip those.
            if (isset($item['Error'])) {
                continue;
            }

            $suggestions[] = [
                'id' => $item['Id'] ?? '',
                'type' => $item['Type'] ?? '',
                'text' => $item['Text'] ?? '',
                'description' => $item['Description'] ?? '',
            ];
        }

        return $suggestions;
    }

    /**
     * The full address for a chosen suggestion id, with coordinates from its
     * postcode. Null if Loqate has nothing for the id.
     *
     * @return array{line1: string, line2: string, city: string, postcode: string, latitude: ?float, longitude: ?float}|null
     */
    public function retrieve(string $id): ?array
    {
        $response = Http::get(self::RETRIEVE_URL, [
            'Key' => $this->key(),
            'Id' => $id,
        ]);

        $item = $response->successful() ? ($response->json('Items')[0] ?? null) : null;

        if ($item === null || isset($item['Error'])) {
            return null;
        }

        $postcode = $item['PostalCode'] ?? '';
        $coordinates = $postcode ? $this->geocodePostcode($postcode) : null;

        return [
            'line1' => $item['Line1'] ?? '',
            'line2' => $item['Line2'] ?? '',
            'city' => $item['City'] ?? '',
            'postcode' => $postcode,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
        ];
    }

    /**
     * Coordinates for a UK postcode, or null if Loqate can't place it.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocodePostcode(string $postcode): ?array
    {
        $response = Http::get(self::GEOCODE_URL, [
            'Key' => $this->key(),
            'Location' => $postcode,
        ]);

        $item = $response->successful() ? ($response->json('Items')[0] ?? null) : null;

        if ($item === null || ! isset($item['Latitude'], $item['Longitude'])) {
            return null;
        }

        return [
            'latitude' => (float) $item['Latitude'],
            'longitude' => (float) $item['Longitude'],
        ];
    }

    /**
     * Best-effort coordinates for a free-text address, used when a user won't
     * share their location. If the text already holds a postcode we geocode
     * that straight away; otherwise we look the address up and geocode the
     * first proper match. Null if nothing places it.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function coordinatesForAddress(string $address): ?array
    {
        $postcode = $this->postcodeIn($address);

        if ($postcode !== null) {
            return $this->geocodePostcode($postcode);
        }

        foreach ($this->search($address) as $suggestion) {
            if ($suggestion['type'] === 'Address') {
                $full = $this->retrieve($suggestion['id']);

                if ($full !== null && $full['latitude'] !== null && $full['longitude'] !== null) {
                    return ['latitude' => $full['latitude'], 'longitude' => $full['longitude']];
                }
            }
        }

        return null;
    }

    /**
     * Pull a UK postcode out of free text, or null if there isn't one. The
     * pattern is the standard UK format (e.g. BB11 1BD), allowing the space to
     * be missing.
     */
    private function postcodeIn(string $text): ?string
    {
        if (preg_match('/[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}/i', $text, $match) === 1) {
            return strtoupper($match[0]);
        }

        return null;
    }

    private function key(): string
    {
        return (string) config('services.loqate.key');
    }
}
