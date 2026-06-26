<?php

namespace App\Http\Controllers;

use App\Services\Vehicle\VehicleLookupService;
use App\Support\WarrantyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    /**
     * Price the warranty for a specific car. Looks the reg up to find the car's
     * age and mileage, picks the Warranty Wise plan it qualifies for, and returns
     * the term options. A car outside every plan comes back `eligible: false` so
     * the app can offer a custom/classic-car route instead.
     */
    public function quote(Request $request, VehicleLookupService $lookup): JsonResponse
    {
        $validated = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
            'mileage' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = $lookup->lookup($validated['registration']);
        $mileage = $validated['mileage'] ?? $vehicle?->mileage;
        $plan = WarrantyPlan::resolve($vehicle?->year, $mileage);

        $base = [
            'registration' => strtoupper(str_replace(' ', '', $validated['registration'])),
            'make' => $vehicle?->make,
            'model' => $vehicle?->model,
        ];

        if ($plan === null) {
            // Too old or too many miles (or we couldn't read the car).
            return response()->json([...$base, 'eligible' => false]);
        }

        $discount = config('warranty.upfront_discount');

        $terms = array_map(function (array $term) use ($discount) {
            $full = $term['monthly'] * $term['months'];

            return [
                'months' => $term['months'],
                'monthly' => round($term['monthly'], 2),
                'upfront' => round($full * (1 - $discount), 2),
                'upfrontSaving' => round($full * $discount, 2),
            ];
        }, config('warranty.terms'));

        return response()->json([
            ...$base,
            'eligible' => true,
            'tier' => $plan['tier'],
            'terms' => $terms,
        ]);
    }

    /**
     * The signed-in customer's current warranty agreement, from the DB. Returns
     * 404 if they don't have one yet.
     */
    public function current(Request $request): JsonResponse
    {
        $agreement = $request->user()->agreements()->latest()->first();

        if ($agreement === null) {
            return response()->json(['message' => 'No warranty on this account yet.'], 404);
        }

        $number = $agreement->agreement_number;
        $formatted = preg_match('/^\d{10}$/', $number)
            ? 'WW-'.substr($number, 0, 4).'-'.substr($number, 4)
            : $number;

        return response()->json([
            'agreementNumber' => $formatted,
            'tier' => $agreement->tier,
            'isActive' => $agreement->status === 'active',
            'startDate' => $agreement->start_date->toDateString(),
            'expiryDate' => $agreement->expiry_date->toDateString(),
            'claimLimit' => $agreement->claim_limit,
            'monthlyPrice' => (float) $agreement->monthly_price,
        ]);
    }
}
