<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;

class AccommodationSeasonPositionPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationSeasonPosition $row): bool
    {
        $unit = AccommodationSeasonUnit::query()->find($row->season_unit_id);

        return $unit !== null && $this->seesFacility($actor, (int) $unit->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function update(User $actor, AccommodationSeasonPosition $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }

    public function delete(User $actor, AccommodationSeasonPosition $row): bool
    {
        return $this->verb($actor, 'accommodation.manage_season_allocation');
    }
}
