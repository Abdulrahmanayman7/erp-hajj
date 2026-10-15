<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;

class SeasonPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'seasons.view');
    }

    public function view(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.view', $season);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'seasons.create');
    }

    public function update(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.update', $season);
    }

    public function delete(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.delete', $season);
    }

    public function activate(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.activate', $season);
    }

    public function close(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.close', $season);
    }

    public function reopen(User $actor, Season $season): bool
    {
        return $this->allows($actor, 'seasons.reopen', $season);
    }
}
