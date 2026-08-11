<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Exceptions\InventoryDomainException;

final class InventoryQuantity
{
    /** Validate movement quantity: must be > 0 with max configured decimals. */
    public static function validatePositive(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            throw InventoryDomainException::invalidQuantity();
        }

        $raw = is_string($quantity) ? trim($quantity) : (string) $quantity;

        if (! is_numeric($raw)) {
            throw InventoryDomainException::invalidQuantity();
        }

        if (bccomp($raw, '0', 3) <= 0) {
            throw InventoryDomainException::invalidQuantity();
        }

        $decimals = (int) config('inventory.quantity_decimals', 3);
        if (preg_match('/\.\d{'.($decimals + 1).',}$/', $raw) === 1) {
            throw InventoryDomainException::invalidQuantity();
        }

        return bcadd($raw, '0', $decimals);
    }

    /** @deprecated Use validatePositive for inbound qty or format for balances. */
    public static function normalize(mixed $quantity): string
    {
        return self::validatePositive($quantity);
    }

    /** Validate non-negative quantity (zero allowed) with max configured decimals. */
    public static function validateNonNegative(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            throw InventoryDomainException::invalidQuantity();
        }

        $raw = is_string($quantity) ? trim($quantity) : (string) $quantity;

        if (! is_numeric($raw)) {
            throw InventoryDomainException::invalidQuantity();
        }

        $decimals = (int) config('inventory.quantity_decimals', 3);
        if (bccomp($raw, '0', $decimals) < 0) {
            throw InventoryDomainException::invalidQuantity();
        }

        if (preg_match('/\.\d{'.($decimals + 1).',}$/', $raw) === 1) {
            throw InventoryDomainException::invalidQuantity();
        }

        return bcadd($raw, '0', $decimals);
    }

    /** Format any non-negative balance snapshot (zero allowed). */
    public static function format(string|float|int $value): string
    {
        return self::validateNonNegative($value);
    }

    public static function applyInbound(string $onHand, string $quantity): string
    {
        $decimals = (int) config('inventory.quantity_decimals', 3);

        return bcadd($onHand, $quantity, $decimals);
    }

    public static function applyOutbound(string $onHand, string $quantity): string
    {
        $decimals = (int) config('inventory.quantity_decimals', 3);
        $result = bcsub($onHand, $quantity, $decimals);

        if (bccomp($result, '0', $decimals) < 0) {
            throw InventoryDomainException::insufficientStock();
        }

        return $result;
    }
}
