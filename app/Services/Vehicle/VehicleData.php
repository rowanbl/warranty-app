<?php

namespace App\Services\Vehicle;

/**
 * One car's details, stitched together from the DVSA MOT history and the DVLA
 * enquiry responses. Dates stay as plain yyyy-mm-dd strings, which is what both
 * the API and the clients expect.
 */
readonly class VehicleData
{
    /**
     * @param  array<int, array{date: ?string, result: ?string, mileage: ?int, expiry: ?string}>  $motHistory
     */
    public function __construct(
        public string $registration,
        public ?string $make = null,
        public ?string $model = null,
        public ?int $year = null,
        public ?string $fuel = null,
        public ?string $colour = null,
        public ?int $engineCapacity = null,
        public ?int $mileage = null,
        public ?string $motDue = null,
        public ?string $taxDue = null,
        public array $motHistory = [],
    ) {}

    /**
     * Build from the two raw API payloads. DVSA carries the car details and MOT
     * tests, DVLA fills in tax and MOT due dates.
     *
     * @param  array<string, mixed>  $dvsa
     * @param  array<string, mixed>  $dvla
     */
    public static function fromApi(string $registration, array $dvsa, array $dvla): self
    {
        $motTests = $dvsa['motTests'] ?? [];
        $history = self::motHistory($motTests);

        return new self(
            registration: $dvsa['registration'] ?? $dvla['registrationNumber'] ?? $registration,
            make: $dvsa['make'] ?? $dvla['make'] ?? null,
            model: $dvsa['model'] ?? null,
            year: self::year($dvsa, $dvla),
            fuel: $dvsa['fuelType'] ?? $dvla['fuelType'] ?? null,
            colour: $dvsa['primaryColour'] ?? $dvla['colour'] ?? null,
            engineCapacity: isset($dvsa['engineSize']) ? (int) $dvsa['engineSize'] : ($dvla['engineCapacity'] ?? null),
            mileage: $history[0]['mileage'] ?? null,
            motDue: $dvla['motExpiryDate'] ?? ($motTests[0]['expiryDate'] ?? null),
            taxDue: $dvla['taxDueDate'] ?? null,
            motHistory: $history,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'registration' => $this->registration,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'fuel' => $this->fuel,
            'colour' => $this->colour,
            'engine_capacity' => $this->engineCapacity,
            'mileage' => $this->mileage,
            'mot_due' => $this->motDue,
            'tax_due' => $this->taxDue,
            'mot_history' => $this->motHistory,
        ];
    }

    /**
     * Flatten the MOT tests into our own simple shape, newest first.
     *
     * @param  array<int, array<string, mixed>>  $motTests
     * @return array<int, array{date: ?string, result: ?string, mileage: ?int, expiry: ?string}>
     */
    private static function motHistory(array $motTests): array
    {
        return array_map(fn (array $test) => [
            'date' => $test['completedDate'] ?? null,
            'result' => $test['testResult'] ?? null,
            'mileage' => isset($test['odometerValue']) ? (int) $test['odometerValue'] : null,
            'expiry' => $test['expiryDate'] ?? null,
        ], $motTests);
    }

    /**
     * @param  array<string, mixed>  $dvsa
     * @param  array<string, mixed>  $dvla
     */
    private static function year(array $dvsa, array $dvla): ?int
    {
        if (isset($dvla['yearOfManufacture'])) {
            return (int) $dvla['yearOfManufacture'];
        }

        // DVSA dates look like "2018-03-14", so the first four digits are the year.
        $date = $dvsa['manufactureDate'] ?? $dvsa['registrationDate'] ?? null;

        return $date ? (int) substr($date, 0, 4) : null;
    }
}
