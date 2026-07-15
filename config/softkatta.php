<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central License API URL
    |--------------------------------------------------------------------------
    |
    | Used in customer installation .env files (SOFTKATTA_API_URL).
    |
    */

    'central_api_url' => rtrim(
        env('SOFTKATTA_CENTRAL_API_URL', 'https://softkatta.com/api/v1'),
        '/'
    ),

];
