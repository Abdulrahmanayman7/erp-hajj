<?php

namespace App\Modules\Transportation\Integration;

use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimOperationalReferenceChecker;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\Transportation\Models\TransportRunPassenger;

/**
 * Any passenger row (any status) keeps the SeasonPilgrim from hard delete.
 */
final class TransportationOperationalReferenceChecker implements SeasonPilgrimOperationalReferenceChecker
{
    public function isReferenced(SeasonPilgrim $seasonPilgrim): bool
    {
        return TransportRunPassenger::query()
            ->where('season_id', $seasonPilgrim->season_id)
            ->where('season_pilgrim_id', $seasonPilgrim->id)
            ->exists();
    }
}
