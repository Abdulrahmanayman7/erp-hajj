<?php

namespace App\Modules\Inventory\Enums;

/**
 * Derived stock state helper — not persisted.
 */
enum StockState: string
{
    case Normal = 'normal';
    case Low = 'low';
    case OutOfStock = 'out_of_stock';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public static function fromQuantities(string $onHand, string $minimumStock): self
    {
        if (bccomp($onHand, '0', 3) === 0) {
            return self::OutOfStock;
        }

        if (bccomp($onHand, $minimumStock, 3) <= 0) {
            return self::Low;
        }

        return self::Normal;
    }
}
