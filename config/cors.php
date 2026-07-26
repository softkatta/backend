<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        ['https://softkatta.in', 'https://www.softkatta.in', 'https://api.softkatta.in'],
        array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    )))),

    'allowed_origins_patterns' => ['^https://(?:.+\.)?softkatta\.in$'],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,

];
