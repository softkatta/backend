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
        env('SOFTKATTA_CENTRAL_API_URL', 'https://api.softkatta.in/api/v1'),
        '/'
    ),

    'company_api_url' => rtrim(
        env('SOFTKATTA_COMPANY_API_URL', env('SOFTKATTA_CENTRAL_API_URL', 'https://api.softkatta.in/api/v1').'/company'),
        '/'
    ),

    'company_timestamp_skew' => (int) env('SOFTKATTA_COMPANY_TIMESTAMP_SKEW', 300),

    /*
    |--------------------------------------------------------------------------
    | Super Admin (seeded from .env on migrate --seed / db:seed)
    |--------------------------------------------------------------------------
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@softkatta.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'Admin@123'),
    ],

];
