<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationUnit;

class AccommodationPositionPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationPosition $position): bool
    {
        $unit = AccommodationUnit::query()->find($position->unit_id);

        return $unit !== null && $this->seesFacility($actor, (int) $unit->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function update(User $actor, AccommodationPosition $position): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function delete(User $actor, AccommodationPosition $position): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }
}
