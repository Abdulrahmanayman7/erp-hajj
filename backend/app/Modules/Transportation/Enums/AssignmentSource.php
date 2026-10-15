<?php

namespace App\Modules\Transportation\Enums;

enum AssignmentSource: string
{
    case Individual = 'individual';
    case PilgrimGroup = 'pilgrim_group';
    case FamilyGroup = 'family_group';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
