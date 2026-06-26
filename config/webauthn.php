<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');
$frontendHost = parse_url($frontendUrl, PHP_URL_HOST) ?: 'localhost';

return [
    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'SoftKatta')),
    'rp_id' => env('WEBAUTHN_RP_ID', $frontendHost),
    'origin' => env('WEBAUTHN_ORIGIN', $frontendUrl),
];
