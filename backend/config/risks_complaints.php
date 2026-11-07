<?php

/**
 * S07 Risks & Complaints — engineering configuration.
 *
 * Complaint category codes below are a provisional MVP seed for validation/UI,
 * not a separately Owner-approved taxonomy (Part 1 readiness recommendation).
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 * @see docs/09-modules/21-risks-and-complaints/OWNER_DECISIONS.md
 */
return [
    'risk_number_prefix' => 'RSK-',
    'complaint_number_prefix' => 'CMP-',
    'number_pad' => 6,

    /**
     * @var list<string>
     */
    'complaint_categories' => [
        'service',
        'accommodation',
        'transport',
        'procurement',
        'staffing',
        'safety',
        'other',
    ],
];
