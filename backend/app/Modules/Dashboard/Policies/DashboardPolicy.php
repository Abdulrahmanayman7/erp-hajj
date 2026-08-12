<?php

namespace App\Modules\Dashboard\Policies;

use App\Models\User;

class DashboardPolicy
{
    public function view(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('dashboard.view');
    }
}
