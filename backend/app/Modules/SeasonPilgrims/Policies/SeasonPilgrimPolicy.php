<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

class SeasonPilgrimPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'season_pilgrims.view');
    }

    public function view(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.view', $seasonPilgrim);
    }

    public function register(User $actor): bool
    {
        return $this->allows($actor, 'season_pilgrims.register');
    }

    public function activate(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.activate', $seasonPilgrim);
    }

    public function complete(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.complete', $seasonPilgrim);
    }

    public function cancel(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.cancel', $seasonPilgrim);
    }

    public function delete(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.delete', $seasonPilgrim);
    }

    public function refreshSnapshot(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.refresh_snapshot', $seasonPilgrim);
    }

    public function correctSnapshot(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.correct_snapshot', $seasonPilgrim);
    }

    public function changeProgram(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrims.change_program', $seasonPilgrim);
    }

    public function assignPilgrimGroup(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_pilgrim_groups.assign_members', $seasonPilgrim);
    }

    public function assignFamilyGroup(User $actor, SeasonPilgrim $seasonPilgrim): bool
    {
        return $this->allows($actor, 'season_family_groups.assign_members', $seasonPilgrim);
    }
}
