<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

$allowedOrigins = [$frontendUrl];

$extraOrigins = env('CORS_ALLOWED_ORIGINS');
if (is_string($extraOrigins) && $extraOrigins !== '') {
    foreach (explode(',', $extraOrigins) as $origin) {
        $trimmed = rtrim(trim($origin), '/');
        if ($trimmed !== '') {
            $allowedOrigins[] = $trimmed;
        }
    }
}

// Browsers treat localhost and 127.0.0.1 as distinct origins. Sanctum already
// lists both as stateful domains; CORS must echo whichever host the SPA used.
$frontendHost = parse_url($frontendUrl, PHP_URL_HOST);
if ($frontendHost === 'localhost') {
    $allowedOrigins[] = str_replace('://localhost', '://127.0.0.1', $frontendUrl);
} elseif ($frontendHost === '127.0.0.1') {
    $allowedOrigins[] = str_replace('://127.0.0.1', '://localhost', $frontendUrl);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit SPA origins only (never "*") — credentials are required for
    // Sanctum SPA cookie authentication (HttpOnly session cookie + CSRF).
    'allowed_origins' => array_values(array_unique($allowedOrigins)),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Correlation-ID'],

    'max_age' => 0,

    'supports_credentials' => true,

];
