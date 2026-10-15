<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;

class SeasonPilgrimGroupPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.view');
    }

    public function view(User $actor, SeasonPilgrimGroup $group): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.view', $group);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.manage');
    }

    public function update(User $actor, SeasonPilgrimGroup $group): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.manage', $group);
    }

    public function delete(User $actor, SeasonPilgrimGroup $group): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.manage', $group);
    }

    public function manageSupervisors(User $actor, SeasonPilgrimGroup $group): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.manage_supervisors', $group);
    }
}
