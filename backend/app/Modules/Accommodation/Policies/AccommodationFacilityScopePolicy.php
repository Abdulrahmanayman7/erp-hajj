<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;

class AccommodationFacilityScopePolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function view(User $actor, AccommodationFacilityScope $scope): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function revoke(User $actor, AccommodationFacilityScope $scope): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }
}
