<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Complaint SLA policy activation (U03)
    |--------------------------------------------------------------------------
    |
    | ISO-8601 / parseable datetime. Empty / null = policy DISABLED (no
    | ALT-COMP-SLA evaluation). Prospective only: complaint SLA clocks that
    | start on or after this instant are eligible. Not the Owner approval date.
    |
    */
    'complaint_sla_activated_at' => env('EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT'),

    'complaint_sla_hours' => 48,

];
