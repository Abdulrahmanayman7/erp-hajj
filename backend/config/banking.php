<?php

/**
 * S04 Phase II-B banking security configuration.
 *
 * BANKING_IBAN_PEPPER must be distinct from APP_KEY. Fingerprint helpers fail closed
 * when the pepper is missing or empty (WP-01 foundation).
 */
return [
    'iban_pepper' => env('BANKING_IBAN_PEPPER'),
];
