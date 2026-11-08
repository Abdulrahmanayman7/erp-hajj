<?php

namespace App\Modules\Contracts\Support;

/**
 * The placeholder counterparty written by season template copies (S08-D17).
 * A contract that still carries it is a template stub: it can be edited or cancelled
 * but cannot leave `draft` toward review / approval / execution (Control C).
 */
final class ContractCounterparty
{
    public const TEMPLATE_PLACEHOLDER = 'من القالب';

    public static function isPlaceholder(?string $name): bool
    {
        return $name !== null && trim($name) === self::TEMPLATE_PLACEHOLDER;
    }
}
