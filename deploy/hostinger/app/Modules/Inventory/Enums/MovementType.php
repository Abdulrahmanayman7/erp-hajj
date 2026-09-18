<?php

namespace App\Modules\Inventory\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Return = 'return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';

    public function direction(): MovementDirection
    {
        return match ($this) {
            self::Opening, self::Receipt, self::Return, self::TransferIn => MovementDirection::In,
            self::Issue, self::TransferOut => MovementDirection::Out,
            self::Adjustment => throw new \LogicException('Adjustment direction is payload-driven.'),
        };
    }

    public function isInbound(): bool
    {
        return $this->direction() === MovementDirection::In;
    }
}
