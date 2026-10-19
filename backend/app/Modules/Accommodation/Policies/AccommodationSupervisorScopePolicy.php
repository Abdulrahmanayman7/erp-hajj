<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;

class AccommodationSupervisorScopePolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function view(User $actor, AccommodationSupervisorScope $scope): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }

    public function revoke(User $actor, AccommodationSupervisorScope $scope): bool
    {
        return $this->verb($actor, 'accommodation.manage_scopes');
    }
}
