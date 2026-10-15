<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;

class SeasonProgramPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'season_programs.view');
    }

    public function view(User $actor, SeasonProgram $program): bool
    {
        return $this->allows($actor, 'season_programs.view', $program);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'season_programs.manage');
    }

    public function update(User $actor, SeasonProgram $program): bool
    {
        return $this->allows($actor, 'season_programs.manage', $program);
    }

    public function delete(User $actor, SeasonProgram $program): bool
    {
        return $this->allows($actor, 'season_programs.delete', $program);
    }
}
