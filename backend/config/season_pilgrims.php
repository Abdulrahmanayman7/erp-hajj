<?php

/**
 * Season & Pilgrim foundation (Phase 2, Sprint 1).
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md
 */
return [
    // Seasonal reference number (F32): unique per season, never reused.
    'reference_number_prefix' => 'SP-',
    'reference_number_pad' => 6,

    // Explicit Season Context selection header (F35). Absent → Active season.
    'season_context_header' => 'X-Season-Id',
];
