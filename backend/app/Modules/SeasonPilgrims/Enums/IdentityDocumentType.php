<?php

namespace App\Modules\SeasonPilgrims\Enums;

/**
 * F17 conceptual types. Further types require an approved change.
 */
enum IdentityDocumentType: string
{
    case Passport = 'passport';
    case NationalId = 'national_id';
    case ResidencePermit = 'residence_permit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
