<?php

/**
 * Transportation & Fleet (Phase 2, backend sprint 1).
 *
 * @see docs/09-modules/17-transportation-and-fleet/BUSINESS_RULES.md
 * @see docs/10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md
 */
return [
    'vehicle_number_prefix' => 'VEH-',
    'movement_number_prefix' => 'MOV-',
    'number_pad' => 6,

    // I7: an occurred_at older than this is backdated (reason + correct_history).
    'backdate_tolerance_minutes' => (int) env('TRANSPORT_BACKDATE_TOLERANCE_MINUTES', 15),
];
