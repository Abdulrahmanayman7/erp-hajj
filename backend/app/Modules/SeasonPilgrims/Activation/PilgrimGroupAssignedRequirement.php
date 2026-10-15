<?php

namespace App\Modules\SeasonPilgrims\Activation;

use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimActivationRequirement;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * F29: a Pilgrim Group is required before Registered → Active.
 */
final class PilgrimGroupAssignedRequirement implements SeasonPilgrimActivationRequirement
{
    public function check(SeasonPilgrim $seasonPilgrim): ?string
    {
        return $seasonPilgrim->season_pilgrim_group_id === null ? 'PILGRIM_GROUP_REQUIRED' : null;
    }
}
