<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;

class AccommodationSeasonFacilityPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationSeasonFacility $row): bool
    {
        return $this->seesFacility($actor, (int) $row->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function update(User $actor, AccommodationSeasonFacility $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function delete(User $actor, AccommodationSeasonFacility $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }
}
