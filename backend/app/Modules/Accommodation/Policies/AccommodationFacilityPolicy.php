<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;

class AccommodationFacilityPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationFacility $facility): bool
    {
        return $this->seesFacility($actor, (int) $facility->id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function update(User $actor, AccommodationFacility $facility): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function delete(User $actor, AccommodationFacility $facility): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }
}
