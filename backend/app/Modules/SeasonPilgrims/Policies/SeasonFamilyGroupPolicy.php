<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;

class SeasonFamilyGroupPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'season_family_groups.view');
    }

    public function view(User $actor, SeasonFamilyGroup $group): bool
    {
        return $this->allows($actor, 'season_family_groups.view', $group);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'season_family_groups.manage');
    }

    public function update(User $actor, SeasonFamilyGroup $group): bool
    {
        return $this->allows($actor, 'season_family_groups.manage', $group);
    }

    public function delete(User $actor, SeasonFamilyGroup $group): bool
    {
        return $this->allows($actor, 'season_family_groups.manage', $group);
    }
}
