<?php

namespace App\Services\Vehicle;

use Illuminate\Support\Facades\Http;

/**
 * Looks a car up by registration. Mirrors the partsync approach: get an OAuth
 * token, pull MOT history from the DVSA, and the tax and MOT dates from the
 * DVLA. Returns null if the car can't be found or the lookup fails.
 */
class VehicleLookupService
{
    public function lookup(string $registration): ?VehicleData
    {
        $registration = $this->normalise($registration);

        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        $dvsa = $this->fetchMotHistory($registration, $token);

        if ($dvsa === null) {
            return null;
        }

        $dvla = $this->fetchVehicleEnquiry($registration) ?? [];

        return VehicleData::fromApi($registration, $dvsa, $dvla);
    }

    private function accessToken(): ?string
    {
        $response = Http::asForm()->post(config('vehicle.vin_api_url'), [
            'client_id' => config('vehicle.vin_client_id'),
            'client_secret' => config('vehicle.vin_client_secret'),
            'grant_type' => 'client_credentials',
            'scope' => config('vehicle.vin_free_scope'),
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMotHistory(string $registration, string $token): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'x-api-key' => config('vehicle.vin_free_api_key'),
            'Accept' => 'application/json',
        ])->get(config('vehicle.vrm_free_data_url').$registration);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchVehicleEnquiry(string $registration): ?array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('vehicle.dvla_x_api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles', [
            'registrationNumber' => $registration,
        ]);

        return $response->successful() ? $response->json() : null;
    }

    // Both APIs want the plate with no spaces and in upper case.
    private function normalise(string $registration): string
    {
        return strtoupper(str_replace(' ', '', trim($registration)));
    }
}
