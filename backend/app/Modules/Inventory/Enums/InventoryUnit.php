<?php

namespace App\Modules\Inventory\Enums;

enum InventoryUnit: string
{
    case Piece = 'piece';
    case Box = 'box';
    case Pack = 'pack';
    case Set = 'set';
    case Kg = 'kg';
    case G = 'g';
    case Liter = 'liter';
    case Ml = 'ml';
    case Meter = 'meter';
    case Cm = 'cm';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $u): string => $u->value, self::cases());
    }

    public static function isAllowed(string $unit): bool
    {
        $config = config('inventory.units', self::values());

        return in_array($unit, $config, true);
    }
}
