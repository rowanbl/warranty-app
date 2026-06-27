<?php

// Fuel Finder open-data API. Live fuel prices published under the Motor Fuel
// Price (Open Data) Regulations 2025, served by the government scheme operator
// and refreshed within minutes of a price change.
//
// Access is OAuth client-credentials: POST the client id + secret to the token
// endpoint for a short-lived Bearer token, then call the data feed with it.
// URLs are env-driven so they can be corrected against the scheme's API docs
// without a code change.
return [
    'token_url' => env('FUEL_FINDER_TOKEN_URL', 'https://www.fuel-finder.service.gov.uk/api/v1/oauth/generate_access_token'),
    'feed_url' => env('FUEL_FINDER_FEED_URL', 'https://www.fuel-finder.service.gov.uk/api/v1/pfs/fuel-prices'),
    'client_id' => env('FUEL_FINDER_CLIENT_ID', ''),
    'client_secret' => env('FUEL_FINDER_CLIENT_SECRET', ''),
    'scope' => env('FUEL_FINDER_SCOPE', 'fuelfinder.read'),

    // How far out to look, and the most stations we'll ever return, so a wide
    // radius can't flood the app.
    'default_radius_miles' => 5,
    'max_results' => 50,

    // Ingest. The price feed has no location and comes in pages of 500, so we
    // pull it on a schedule into our own table and geocode each forecourt once
    // from its trading name. `max_batches` caps the paging; `geocode_per_run`
    // caps how many newly-seen stations we geocode each run, so the Loqate bill
    // is bounded and the backlog clears over a few runs.
    'max_batches' => (int) env('FUEL_FINDER_MAX_BATCHES', 60),
    'geocode_per_run' => (int) env('FUEL_FINDER_GEOCODE_PER_RUN', 200),
];
