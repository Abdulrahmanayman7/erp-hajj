<?php

namespace App\Modules\OperationalKpis\Policies;

use App\Models\User;

/**
 * Page-level gate for the S09 Operational KPIs page (D08-Q1). Domain
 * permissions (`*.view`) are applied per KPI by the orchestrator; this gate
 * deliberately does not accept `dashboard.view` as a substitute.
 */
class OperationalKpiPolicy
{
    public function view(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('operational_kpis.view');
    }
}
