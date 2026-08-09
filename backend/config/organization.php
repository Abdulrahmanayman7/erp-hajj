<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum organization unit depth (MVP application safety constraint)
    |--------------------------------------------------------------------------
    |
    | Root units have depth 0. A direct child of a root has depth 1, etc.
    | This is NOT a database architectural limit — see ADR-0004 and
    | docs/09-modules/03-organization-structure/BUSINESS_RULES.md.
    |
    */
    'max_depth' => (int) env('ORGANIZATION_UNIT_MAX_DEPTH', 8),
];
