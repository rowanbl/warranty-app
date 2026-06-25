<?php

// Warranty pricing. The base product is paid monthly with a minimum term, and
// longer terms are cheaper per month (less flexibility, lower price). Paying the
// whole term upfront earns a discount. For now the monthly prices are the same
// for every car, but the quote endpoint looks the car up so this can vary by
// make/model later without any app change.
return [
    // Fraction off when the customer pays the whole term upfront.
    'upfront_discount' => 0.10,

    // Minimum term first. Longer term, lower monthly.
    'terms' => [
        ['months' => 12, 'monthly' => 49.99],
        ['months' => 24, 'monthly' => 44.99],
        ['months' => 36, 'monthly' => 39.99],
        ['months' => 60, 'monthly' => 34.99],
    ],
];
