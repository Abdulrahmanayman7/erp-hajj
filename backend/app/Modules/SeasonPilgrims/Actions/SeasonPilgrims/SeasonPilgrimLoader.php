<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * Detail eager-load set for SeasonPilgrim responses (avoids N+1).
 */
final class SeasonPilgrimLoader
{
    /**
     * @var list<string>
     */
    public const DETAIL_RELATIONS = [
        'pilgrim',
        'program',
        'pilgrimGroup',
        'familyGroup',
        'snapshot',
        'programChanges.fromProgram',
        'programChanges.toProgram',
    ];

    public static function load(SeasonPilgrim $seasonPilgrim): SeasonPilgrim
    {
        return $seasonPilgrim->refresh()->load(self::DETAIL_RELATIONS);
    }
}
