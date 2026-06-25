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
        $validated = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
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
}
