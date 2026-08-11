<?php

namespace App\Modules\Assets\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Damaged = 'damaged';
    case Retired = 'retired';
    case Lost = 'lost';

    public function isTerminal(): bool
    {
        return $this === self::Retired || $this === self::Lost;
    }

    public function canAssign(): bool
    {
        return $this === self::Available;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Allowed next_status values on custody return.
     *
     * @return list<string>
     */
    public static function returnNextStatuses(): array
    {
        return [
            self::Available->value,
            self::Maintenance->value,
            self::Damaged->value,
            self::Retired->value,
        ];
    }
}
