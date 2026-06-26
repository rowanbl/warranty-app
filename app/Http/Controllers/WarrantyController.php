<?php

namespace App\Http\Controllers;

use App\Services\Vehicle\VehicleLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    /**
     * Price the warranty for a specific car. Looks the reg up so the price can
     * depend on the make/model (same for every car today). Returns the term
     * options: monthly per term, and the upfront price + saving for each.
     */
    public function quote(Request $request, VehicleLookupService $lookup): JsonResponse
    {
        // Mileage comes through so pricing can vary by it later. For now the
        // prices are the same, but the make/model and mileage are all here.
        $validated = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
            'mileage' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = $lookup->lookup($validated['registration']);

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
            'registration' => strtoupper(str_replace(' ', '', $validated['registration'])),
            'make' => $vehicle?->make,
            'model' => $vehicle?->model,
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
            'tier' => ucfirst($agreement->tier->value),
            'isActive' => $agreement->status === 'active',
            'startDate' => $agreement->start_date->toDateString(),
            'expiryDate' => $agreement->expiry_date->toDateString(),
            'claimLimit' => $agreement->claim_limit,
            'monthlyPrice' => (float) $agreement->monthly_price,
        ]);
    }
}
