<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Enums\StockState;

final class StockStateResolver
{
    public static function resolve(string|float|int $onHand, string|float|int $minimumStock): StockState
    {
        $onHandNorm = InventoryQuantity::format((string) $onHand);
        $minimumNorm = InventoryQuantity::format((string) $minimumStock);

        if (bccomp($onHandNorm, '0', 3) === 0) {
            return StockState::OutOfStock;
        }

        if (bccomp($onHandNorm, $minimumNorm, 3) <= 0) {
            return StockState::Low;
        }

        return StockState::Normal;
    }
}
