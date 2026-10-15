<?php

namespace App\Modules\SeasonPilgrims\Activation;

use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimActivationRequirement;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * F20: activation locks the seasonal identity snapshot, so one must exist.
 * Which identity fields must be filled is NOT decided — add a dedicated
 * requirement once approved.
 */
final class IdentitySnapshotPresentRequirement implements SeasonPilgrimActivationRequirement
{
    public function check(SeasonPilgrim $seasonPilgrim): ?string
    {
        return $seasonPilgrim->snapshot()->exists() ? null : 'IDENTITY_SNAPSHOT_REQUIRED';
    }
}
