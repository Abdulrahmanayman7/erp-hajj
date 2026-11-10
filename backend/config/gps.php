<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GPS provider
    |--------------------------------------------------------------------------
    |
    | Bound implementation of GpsProviderInterface. `null` selects NullGpsProvider
    | (no live fetch). G4 Live vendor binding remains BLOCKED until a real source
    | is approved (ADR-0021).
    |
    */
    'provider' => env('GPS_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Ingest webhook HMAC secret
    |--------------------------------------------------------------------------
    |
    | Fail-closed: empty secret rejects all ingest requests.
    |
    */
    'webhook_secret' => env('GPS_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Freshness thresholds (G2)
    |--------------------------------------------------------------------------
    |
    | REAL when age <= freshness_real_seconds; otherwise STALE. UNAVAILABLE when
    | no last-known row exists.
    |
    */
    'freshness_real_seconds' => (int) env('GPS_FRESHNESS_REAL_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Ingest replay nonce TTL
    |--------------------------------------------------------------------------
    */
    'nonce_ttl_seconds' => (int) env('GPS_NONCE_TTL_SECONDS', 600),

];
