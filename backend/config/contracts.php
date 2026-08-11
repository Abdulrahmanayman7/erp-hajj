<?php

return [
    'number_prefix' => env('CONTRACT_NUMBER_PREFIX', 'CTR-'),
    'number_pad' => (int) env('CONTRACT_NUMBER_PAD', 6),
    'default_currency' => env('CONTRACT_DEFAULT_CURRENCY', 'SAR'),
    'expiring_soon_days' => (int) env('CONTRACT_EXPIRING_SOON_DAYS', 30),
];
