<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central License API URL
    |--------------------------------------------------------------------------
    |
    | Used in customer installation .env files (SOFTKATTA_API_URL).
    | Prefer the company API base (.../api/v1/company) for new products.
    |
    */

    'central_api_url' => rtrim(
        env('SOFTKATTA_CENTRAL_API_URL', 'https://softkatta.com/api/v1'),
        '/'
    ),

    'company_api_url' => rtrim(
        env('SOFTKATTA_COMPANY_API_URL', env('SOFTKATTA_CENTRAL_API_URL', 'https://softkatta.com/api/v1').'/company'),
        '/'
    ),

    'company_timestamp_skew' => (int) env('SOFTKATTA_COMPANY_TIMESTAMP_SKEW', 300),

];
