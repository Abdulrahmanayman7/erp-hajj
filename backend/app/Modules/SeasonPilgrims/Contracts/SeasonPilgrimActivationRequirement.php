<?php

namespace App\Modules\SeasonPilgrims\Contracts;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * One Registered → Active requirement (F20, F29). Implementations are
 * registered under the `season_pilgrims.activation_requirements` tag so
 * that approved identity-field requirements can be added later without
 * changing the activation action.
 */
interface SeasonPilgrimActivationRequirement
{
    /**
     * Stable violation code, or null when the requirement is satisfied.
     */
    public function check(SeasonPilgrim $seasonPilgrim): ?string;
}
