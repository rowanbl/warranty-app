<?php

// Warranty pricing. The base product is paid monthly with a minimum term, and
// longer terms are cheaper per month (less flexibility, lower price). Paying the
// whole term upfront earns a discount. For now the monthly prices are the same
// for every car, but the quote endpoint looks the car up so this can vary by
// make/model later without any app change.
return [
    // Fraction off when the customer pays the whole term upfront.
    'upfront_discount' => 0.10,

    'claim_limit' => 7500,

    // The Warranty Wise plans. A car gets the first (smallest) plan it fits, by
    // age AND mileage. A car outside every plan (older than 15 years or over
    // 150,000 miles) can't be offered cover. The tier label is the plan name.
    'plans' => [
        ['tier' => '04/40', 'max_age' => 4, 'max_mileage' => 40000],
        ['tier' => '06/60', 'max_age' => 6, 'max_mileage' => 60000],
        ['tier' => '08/80', 'max_age' => 8, 'max_mileage' => 80000],
        ['tier' => '10/100', 'max_age' => 10, 'max_mileage' => 100000],
        ['tier' => '15/150', 'max_age' => 15, 'max_mileage' => 150000],
    ],

    // Minimum term first. Longer term, lower monthly.
    'terms' => [
        ['months' => 12, 'monthly' => 49.99],
        ['months' => 24, 'monthly' => 44.99],
        ['months' => 36, 'monthly' => 39.99],
        ['months' => 60, 'monthly' => 34.99],
    ],
];
