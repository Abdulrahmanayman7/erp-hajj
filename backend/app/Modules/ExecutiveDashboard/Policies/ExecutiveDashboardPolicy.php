<?php

namespace App\Modules\ExecutiveDashboard\Policies;

use App\Models\User;

/**
 * Page-level gate for the S10 Executive Dashboard (S10-D02 = C).
 * Requires BOTH executive_dashboard.view AND operational_kpis.view — never OR,
 * never dashboard.view as a substitute.
 */
class ExecutiveDashboardPolicy
{
    public function view(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('executive_dashboard.view')
            && $actor->hasPermission('operational_kpis.view');
    }
}
