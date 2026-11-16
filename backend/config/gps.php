<?php

$decodeWebhookSecrets = static function (): array {
    $raw = env('GPS_WEBHOOK_SECRETS');
    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
};

$decodeAllowlist = static function (): array {
    $raw = env('GPS_OUTBOUND_HOST_ALLOWLIST', '');
    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
};

return [

    /*
    |--------------------------------------------------------------------------
    | GPS provider (legacy process-wide pull binding)
    |--------------------------------------------------------------------------
    |
    | Bound implementation of GpsProviderInterface. `null` selects NullGpsProvider
    | (no live fetch). G4 Live vendor binding remains BLOCKED until authorized.
    | Tenant-configurable hub providers live in `gps_providers` and use adapters.
    |
    */
    'provider' => env('GPS_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Live outbound authorization (G4)
    |--------------------------------------------------------------------------
    |
    | REST/Traccar live HTTP remains fail-closed unless explicitly authorized.
    | Production additionally requires GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION.
    | Owner authorization to claim G4 Live COMPLETE is still a separate act.
    |
    */
    'live_outbound_authorized' => (bool) env('GPS_LIVE_OUTBOUND_AUTHORIZED', false),
    'live_outbound_authorized_in_production' => (bool) env('GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Simulator / DEMO mode
    |--------------------------------------------------------------------------
    |
    | Disabled by default. Always blocked in production regardless of this flag.
    |
    */
    'simulator_enabled' => (bool) env('GPS_SIMULATOR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTPS host allowlist (SSRF)
    |--------------------------------------------------------------------------
    |
    | Comma-separated hosts/suffixes. Empty = any public HTTPS host (still
    | rejects private/reserved IPs and non-HTTPS). Prefer an explicit allowlist
    | before enabling live outbound.
    |
    */
    'outbound_host_allowlist' => $decodeAllowlist(),

    /*
    |--------------------------------------------------------------------------
    | Per-tenant ingest webhook HMAC secrets (legacy env map)
    |--------------------------------------------------------------------------
    |
    | Fail-closed: missing/empty secret for the claimed tenant rejects ingest.
    | Hub webhook providers may also store encrypted per-provider secrets.
    |
    */
    'webhook_secrets' => $decodeWebhookSecrets(),

    /*
    |--------------------------------------------------------------------------
    | Freshness thresholds (G2)
    |--------------------------------------------------------------------------
    */
    'freshness_real_seconds' => (int) env('GPS_FRESHNESS_REAL_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Ingest replay nonce TTL
    |--------------------------------------------------------------------------
    */
    'nonce_ttl_seconds' => (int) env('GPS_NONCE_TTL_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | recorded_at acceptance window
    |--------------------------------------------------------------------------
    */
    'max_recorded_at_age_seconds' => (int) env('GPS_MAX_RECORDED_AT_AGE_SECONDS', 600),
    'max_recorded_at_future_seconds' => (int) env('GPS_MAX_RECORDED_AT_FUTURE_SECONDS', 60),

];
