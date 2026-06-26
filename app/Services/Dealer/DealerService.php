<?php

namespace App\Services\Dealer;

use App\Enums\AccountType;
use App\Models\Agreement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleLookupService;
use App\Support\WarrantyPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A dealer registering a customer. It's a direct registration: the dealer fills
 * the whole account out (user, car, bank, warranty agreement) in one go, and we
 * email the customer a verification magic link. They sign in later with their
 * agreement number once they've verified. There's no separate "handover" record
 * — the agreement number is the durable thing.
 */
class DealerService
{
    public function __construct(private VehicleLookupService $lookup) {}

    /**
     * Create the customer's whole account and return their warranty agreement
     * (which carries the agreement number). All or nothing.
     *
     * @param  array{customer: array, vehicle: array, bank: array, warranty: array}  $data
     */
    public function registerCustomer(array $data): Agreement
    {
        return DB::transaction(function () use ($data) {
            $customer = $this->createCustomer($data['customer']);
            $vehicle = $this->createVehicle($customer, $data['vehicle']);
            $this->createBankDetail($customer, $data['bank']);
            $agreement = $this->createAgreement($customer, $vehicle, $data['warranty']);

            // Standard Laravel email verification: the magic link the customer
            // clicks to be allowed to sign in.
            $customer->sendEmailVerificationNotification();

            return $agreement;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: string, address?: string}  $details
     */
    private function createCustomer(array $details): User
    {
        $customer = User::create([
            'name' => $details['name'],
            'email' => Str::lower($details['email']),
            'account_type' => AccountType::Customer,
        ]);

        $customer->customer()->create([
            'phone' => $details['phone'] ?? null,
            'address' => $details['address'] ?? null,
        ]);

        return $customer;
    }

    /**
     * @param  array{registration: string, mileage?: int, insurance_renewal?: string, last_service?: string}  $details
     */
    private function createVehicle(User $customer, array $details): Vehicle
    {
        $found = $this->lookup->lookup($details['registration']);

        // Start from the real lookup if we got one, then layer on what the
        // dealer typed. A dealer-entered mileage is more current than the MOT.
        $attributes = $found ? $found->toArray() : ['registration' => $details['registration']];

        $attributes['mileage'] = $details['mileage'] ?? $attributes['mileage'] ?? null;
        $attributes['insurance_renewal'] = $details['insurance_renewal'] ?? null;
        $attributes['last_service'] = $details['last_service'] ?? null;

        return $customer->vehicles()->create($attributes);
    }

    /**
     * @param  array{term_months: int, monthly: float}  $warranty
     */
    private function createAgreement(User $customer, Vehicle $vehicle, array $warranty): Agreement
    {
        // The plan is decided by the car's age + mileage. A car outside every plan
        // can't be covered, so registration is refused (the app blocks this first).
        $plan = WarrantyPlan::resolve($vehicle->year, $vehicle->mileage);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'vehicle' => 'This vehicle is outside our warranty plans (too old or too many miles).',
            ]);
        }

        $months = (int) $warranty['term_months'];

        return $customer->agreements()->create([
            'vehicle_id' => $vehicle->id,
            'agreement_number' => $this->generateAgreementNumber(),
            'tier' => $plan['tier'],
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addMonths($months)->toDateString(),
            'claim_limit' => config('warranty.claim_limit'),
            'monthly_price' => $warranty['monthly'],
        ]);
    }

    /**
     * @param  array{account_name: string, sort_code: string, account_number: string}  $bank
     */
    private function createBankDetail(User $customer, array $bank): void
    {
        $customer->bankDetail()->create([
            'account_name' => $bank['account_name'],
            'sort_code' => $bank['sort_code'],
            'account_number' => $bank['account_number'],
        ]);
    }

    // A plain 10-digit number (4 then 6), stored as bare digits. The apps show it
    // as WW-XXXX-XXXXXX. It's the customer's agreement number and login.
    private function generateAgreementNumber(): string
    {
        do {
            $number = sprintf('%04d%06d', random_int(0, 9999), random_int(0, 999999));
        } while (Agreement::where('agreement_number', $number)->exists());

        return $number;
    }
}
