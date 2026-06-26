<?php

namespace App\Services\Dealer;

use App\Enums\AccountType;
use App\Models\Address;
use App\Models\Agreement;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AgreementAddedNotification;
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
            $customer = $this->resolveCustomer($data['customer']);
            // Eloquent flags this true only when resolveCustomer just created the
            // account, so it cleanly tells a fresh customer from a returning one.
            $isNewAccount = $customer->wasRecentlyCreated;

            $vehicle = $this->createVehicle($customer, $data['vehicle']);
            $address = $this->resolveAddress($customer, $data['customer']['address'] ?? null);
            $agreement = $this->createAgreement($customer, $vehicle, $address, $data['warranty']);
            $this->createBankDetail($agreement, $data['bank']);

            if ($isNewAccount) {
                // Fresh account: the usual verify-your-email magic link.
                $customer->sendEmailVerificationNotification();
            } else {
                // Existing account gaining another car and Direct Debit. Tell them
                // plainly, so a mistyped email can't quietly attach a car to the
                // wrong person.
                $customer->notify(new AgreementAddedNotification($agreement));
            }

            return $agreement;
        });
    }

    /**
     * The customer this agreement is for. A new email creates the account; an
     * email already on a customer account is reused, so a returning customer
     * just gains another agreement. Their name is left as it stands.
     *
     * @param  array{name: string, email: string, phone?: string, address?: array}  $details
     */
    private function resolveCustomer(array $details): User
    {
        $email = Str::lower($details['email']);
        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->account_type !== AccountType::Customer) {
                throw ValidationException::withMessages([
                    'customer.email' => 'That email already belongs to a non-customer account.',
                ]);
            }

            return $existing;
        }

        $customer = User::create([
            'name' => $details['name'],
            'email' => $email,
            'account_type' => AccountType::Customer,
        ]);

        $customer->customer()->create([
            'phone' => $details['phone'] ?? null,
        ]);

        return $customer;
    }

    /**
     * Store the agreement's address against the customer, or null if the dealer
     * didn't enter one.
     *
     * @param  array<string, mixed>|null  $address
     */
    private function resolveAddress(User $customer, ?array $address): ?Address
    {
        if (empty($address)) {
            return null;
        }

        return $customer->rememberAddress($address);
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
    private function createAgreement(User $customer, Vehicle $vehicle, ?Address $address, array $warranty): Agreement
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
            'address_id' => $address?->id,
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
    private function createBankDetail(Agreement $agreement, array $bank): void
    {
        $agreement->bankDetail()->create([
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
