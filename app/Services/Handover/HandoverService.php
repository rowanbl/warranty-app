<?php

namespace App\Services\Handover;

use App\Enums\AccountType;
use App\Models\Handover;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\HandoverCodeNotification;
use App\Services\Vehicle\VehicleLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The dealer handover. A dealer prepares a customer's whole account in one go,
 * we look the car up for real details, and the customer claims it later with a
 * WW ID and an emailed code.
 */
class HandoverService
{
    public function __construct(private VehicleLookupService $lookup) {}

    /**
     * Build the customer's account from what the dealer entered and hand back
     * the saved handover. Everything is created together or not at all.
     *
     * @param  array{customer: array, vehicle: array, bank: array, cover?: array, monthly_price?: float, commission?: float}  $data
     */
    public function submit(User $dealer, array $data): Handover
    {
        $code = $this->generateCode();

        return DB::transaction(function () use ($dealer, $data, $code) {
            $customer = $this->createCustomer($data['customer']);

            $vehicle = $this->createVehicle($customer, $data['vehicle']);
            $this->createBankDetail($customer, $data['bank']);

            $handover = Handover::create([
                'ww_id' => $this->generateWwId(),
                'code_hash' => Hash::make($code),
                'dealer_id' => $dealer->id,
                'customer_id' => $customer->id,
                'cover' => $data['cover'] ?? [],
                'monthly_price' => $data['monthly_price'] ?? 0,
                'commission' => $data['commission'] ?? 0,
            ]);

            if (isset($data['warranty'])) {
                $this->createAgreement($customer, $vehicle, $handover->ww_id, $data['warranty']);
            }

            $customer->notify(new HandoverCodeNotification($handover->ww_id, $code));

            return $handover;
        });
    }

    /**
     * Claim a prepared account with its WW ID and code. Verifies the email
     * (the code proves they own it) and marks the handover claimed.
     */
    public function redeem(string $wwId, string $code): Handover
    {
        $handover = Handover::where('ww_id', $this->normaliseWwId($wwId))->first();
        // $wwId may arrive formatted (WW-4471-228901) or plain; both match.

        if (! $handover || $handover->isClaimed() || ! Hash::check($code, $handover->code_hash)) {
            throw ValidationException::withMessages([
                'code' => 'That WW ID and code don\'t match.',
            ]);
        }

        $customer = $handover->customer;

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
        }

        $handover->update(['claimed_at' => now()]);

        return $handover;
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
     * Create the customer's warranty agreement. Its number is the WW ID, so the
     * agreement number and the handover code are one and the same.
     *
     * @param  array{term_months: int, monthly: float}  $warranty
     */
    private function createAgreement(User $customer, Vehicle $vehicle, string $wwId, array $warranty): void
    {
        $months = (int) $warranty['term_months'];

        $customer->agreements()->create([
            'vehicle_id' => $vehicle->id,
            'agreement_number' => $wwId,
            'tier' => config('warranty.default_tier'),
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

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // A plain 10-digit code (4 then 6). Stored without the "WW-" or dashes, so
    // it's just digits. The apps format it as WW-XXXX-XXXXXX for display. Used
    // as both the WW ID and the agreement number.
    private function generateWwId(): string
    {
        do {
            $id = sprintf('%04d%06d', random_int(0, 9999), random_int(0, 999999));
        } while (Handover::where('ww_id', $id)->exists());

        return $id;
    }

    // Accept the code however it's typed or scanned: strip the "WW", dashes and
    // spaces down to the bare digits we stored.
    private function normaliseWwId(string $wwId): string
    {
        return preg_replace('/\D/', '', $wwId) ?? '';
    }
}
