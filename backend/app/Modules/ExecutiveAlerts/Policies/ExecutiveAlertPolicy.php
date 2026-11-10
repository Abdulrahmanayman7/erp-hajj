<?php

namespace App\Modules\ExecutiveAlerts\Policies;

use App\Models\User;

/**
 * Dual page gate + executive_alerts.view.
 * U05=A: acknowledge uses the same permission (no separate .ack).
 * Per-rule domain omit is enforced by ExecutiveAlertVisibility, not this policy.
 */
class ExecutiveAlertPolicy
{
    public function view(User $actor): bool
    {
        return $this->canAccess($actor);
    }

    public function acknowledge(User $actor): bool
    {
        return $this->canAccess($actor);
    }

    private function canAccess(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('executive_dashboard.view')
            && $actor->hasPermission('operational_kpis.view')
            && $actor->hasPermission('executive_alerts.view');
    }
}
