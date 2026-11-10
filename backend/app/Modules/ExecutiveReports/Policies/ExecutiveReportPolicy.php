<?php

namespace App\Modules\ExecutiveReports\Policies;

use App\Models\User;

/**
 * Dual page gate + executive_reports.view.
 * Domain omit for individual reports is enforced by ExecutiveReportsService.
 */
class ExecutiveReportPolicy
{
    public function view(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('executive_dashboard.view')
            && $actor->hasPermission('operational_kpis.view')
            && $actor->hasPermission('executive_reports.view');
    }
}
