<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationUnit;

class AccommodationUnitPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationUnit $unit): bool
    {
        return $this->seesFacility($actor, (int) $unit->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function update(User $actor, AccommodationUnit $unit): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function delete(User $actor, AccommodationUnit $unit): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }
}
