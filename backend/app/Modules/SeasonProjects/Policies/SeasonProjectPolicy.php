<?php

namespace App\Modules\SeasonProjects\Policies;

use App\Models\User;
use App\Modules\SeasonProjects\Models\SeasonProject;

class SeasonProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('season_projects.view');
    }

    public function view(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.view')
            && $this->sameTenant($actor, $project);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('season_projects.create');
    }

    public function update(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.update')
            && $this->sameTenant($actor, $project);
    }

    public function activate(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.activate')
            && $this->sameTenant($actor, $project);
    }

    public function complete(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.complete')
            && $this->sameTenant($actor, $project);
    }

    public function cancel(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.cancel')
            && $this->sameTenant($actor, $project);
    }

    public function reassignOwner(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.reassign_owner')
            && $this->sameTenant($actor, $project);
    }

    public function delete(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.update')
            && $this->sameTenant($actor, $project);
    }

    public function manageBudget(User $actor, ?SeasonProject $project = null): bool
    {
        if (! $actor->hasPermission('season_projects.manage_budget')) {
            return false;
        }

        if ($project === null) {
            return true;
        }

        return $this->sameTenant($actor, $project);
    }

    public function managePhases(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.manage_phases')
            && $this->sameTenant($actor, $project);
    }

    public function linkPo(User $actor, SeasonProject $project): bool
    {
        return $actor->hasPermission('season_projects.link_po')
            && $this->sameTenant($actor, $project);
    }

    private function sameTenant(User $actor, SeasonProject $project): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $project->tenant_id;
    }
}
