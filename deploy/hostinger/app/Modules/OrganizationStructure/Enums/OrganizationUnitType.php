<?php

namespace App\Modules\OrganizationStructure\Enums;

enum OrganizationUnitType: string
{
    case Department = 'department';
    case Section = 'section';
    case Unit = 'unit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
