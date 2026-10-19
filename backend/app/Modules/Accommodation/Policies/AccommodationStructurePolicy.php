<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationStructure;

class AccommodationStructurePolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationStructure $structure): bool
    {
        return $this->seesFacility($actor, (int) $structure->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function update(User $actor, AccommodationStructure $structure): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }

    public function delete(User $actor, AccommodationStructure $structure): bool
    {
        return $this->verb($actor, 'accommodation.manage_facilities');
    }
}
