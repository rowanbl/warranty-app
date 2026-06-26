<?php

namespace App\Support;

/**
 * Works out which Warranty Wise plan a car qualifies for from its age and mileage
 * (the 04/40, 06/60 … 15/150 system). A car gets the first plan it fits; a car
 * outside every plan (too old or too many miles) can't be covered.
 */
class WarrantyPlan
{
    /**
     * @return array{tier: string, max_age: int, max_mileage: int}|null the plan, or null if not eligible
     */
    public static function resolve(?int $year, ?int $mileage): ?array
    {
        // Need both to assess the car. No year (or no mileage) → can't offer.
        if ($year === null || $mileage === null) {
            return null;
        }

        $age = now()->year - $year;

        foreach (config('warranty.plans') as $plan) {
            if ($age <= $plan['max_age'] && $mileage <= $plan['max_mileage']) {
                return $plan;
            }
        }

        return null;
    }
}
