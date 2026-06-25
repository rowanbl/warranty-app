<?php

// Vehicle lookup credentials. Two sources combine into one car record: the DVSA
// MOT history API (reached with an OAuth token) for the car's details and MOT
// mileage history, and the DVLA Vehicle Enquiry Service for tax and MOT dates.
return [
    'vin_api_url' => env('FREE_VRMVIN_API_URL', ''),
    'vin_client_id' => env('FREE_VRMVIN_CLIENT_ID', ''),
    'vin_client_secret' => env('FREE_VRMVIN_CLIENT_SECRET', ''),
    'vin_free_scope' => env('FREE_VRMVIN_SCOPE', ''),
    'vin_free_api_key' => env('FREE_VRMVIN_API_KEY', ''),
    'vin_free_data_url' => env('FREE_VIN_DATA_URL', ''),
    'vrm_free_data_url' => env('FREE_VRM_DATA_URL', ''),
    'dvla_x_api_key' => env('DVLA_X_API_KEY', ''),
];
