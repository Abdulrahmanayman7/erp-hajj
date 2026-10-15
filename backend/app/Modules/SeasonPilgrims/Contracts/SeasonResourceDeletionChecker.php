<?php

namespace App\Modules\SeasonPilgrims\Contracts;

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;

/**
 * Extension point: other domains report historical references that must
 * block a hard delete of a foundation resource. Register implementations
 * under the `season_pilgrims.deletion_reference_checkers` tag. Never
 * consulted for deactivation.
 */
interface SeasonResourceDeletionChecker
{
    public function blocksDeletion(Season|SeasonProgram|SeasonPilgrimGroup|SeasonFamilyGroup $resource): bool;
}
