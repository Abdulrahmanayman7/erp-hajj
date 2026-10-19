<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;

class AccommodationSeasonUnitPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationSeasonUnit $row): bool
    {
        return $this->seesFacility($actor, (int) $row->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function update(User $actor, AccommodationSeasonUnit $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function delete(User $actor, AccommodationSeasonUnit $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }
}
