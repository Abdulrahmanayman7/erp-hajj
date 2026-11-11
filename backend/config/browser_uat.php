<?php

/**
 * Browser UAT attestation — local/testing opt-in only.
 *
 * Never enable in production. The public /api/v1/health endpoint must not
 * expose database identity; UAT harnesses call /api/v1/browser-uat/attestation.
 */
return [
    /*
    | Explicit opt-in. Default false — fail closed unless operators set this.
    */
    'attestation_enabled' => (bool) env('BROWSER_UAT_ATTESTATION_ENABLED', false),

    /*
    | APP_ENV values that may expose attestation when the opt-in flag is true.
    | production / staging / similar are never allowed.
    */
    'allowed_app_envs' => ['local', 'testing'],
];
