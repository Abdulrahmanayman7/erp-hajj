<?php

/**
 * Warehouses & Inventory (Sprint 014).
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md
 */
return [
    'warehouse_number_prefix' => 'WH-',
    'warehouse_number_pad' => 6,

    'item_number_prefix' => 'ITM-',
    'item_number_pad' => 6,

    'movement_number_prefix' => 'MOV-',
    'movement_number_pad' => 6,

    'quantity_decimals' => 3,

    /** MVP always false — configuration switch deferred (ADR-0011). */
    'negative_stock_allowed' => false,

    'units' => [
        'piece',
        'box',
        'pack',
        'set',
        'kg',
        'g',
        'liter',
        'ml',
        'meter',
        'cm',
    ],
];
