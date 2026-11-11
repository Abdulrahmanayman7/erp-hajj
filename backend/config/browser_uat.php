<?php

/**
 * Browser UAT attestation — local/testing opt-in only.
 *
 * Never enable in production. The public /api/v1/health endpoint must not
 * expose database identity; UAT harnesses call /api/v1/browser-uat/attestation
 * with header X-Browser-Uat-Token matching BROWSER_UAT_ATTESTATION_TOKEN.
 */
return [
    /*
    | Explicit opt-in. Default false — fail closed unless operators set this.
    */
    'attestation_enabled' => (bool) env('BROWSER_UAT_ATTESTATION_ENABLED', false),

    /*
    | Shared UAT-only token. Required when attestation is enabled.
    | Empty/missing token ⇒ fail closed. Never commit real values.
    */
    'attestation_token' => (string) env('BROWSER_UAT_ATTESTATION_TOKEN', ''),

    /*
    | APP_ENV values that may expose attestation when the opt-in flag is true
    | and a non-empty token is configured. production / staging are never allowed.
    */
    'allowed_app_envs' => ['local', 'testing'],
];
