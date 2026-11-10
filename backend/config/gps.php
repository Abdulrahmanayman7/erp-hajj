<?php

$decodeWebhookSecrets = static function (): array {
    $raw = env('GPS_WEBHOOK_SECRETS');
    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
};

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
    | Per-tenant ingest webhook HMAC secrets
    |--------------------------------------------------------------------------
    |
    | Fail-closed: missing/empty secret for the claimed tenant rejects ingest.
    | Keys may be tenant_id (string) or tenant_code. No shared global secret —
    | a single GPS_WEBHOOK_SECRET must not authorize cross-tenant updates.
    |
    | Env JSON example: {"1":"secret-for-tenant-1","rafee":"secret-for-rafee"}
    |
    */
    'webhook_secrets' => $decodeWebhookSecrets(),

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
    |
    | Nominal TTL. Effective nonce retention is
    | nonce_ttl_seconds + max_recorded_at_future_seconds + 1 so future-skewed
    | recorded_at values cannot be replayed after the nominal TTL while still
    | inside the acceptance window. Values below 60 are floored to 60 at runtime.
    |
    */
    'nonce_ttl_seconds' => (int) env('GPS_NONCE_TTL_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | recorded_at acceptance window
    |--------------------------------------------------------------------------
    |
    | Reject stale or future device clocks. Effective max age is capped at the
    | nominal nonce TTL. Negative future skew is treated as 0 at runtime.
    |
    */
    'max_recorded_at_age_seconds' => (int) env('GPS_MAX_RECORDED_AT_AGE_SECONDS', 600),
    'max_recorded_at_future_seconds' => (int) env('GPS_MAX_RECORDED_AT_FUTURE_SECONDS', 60),

];
