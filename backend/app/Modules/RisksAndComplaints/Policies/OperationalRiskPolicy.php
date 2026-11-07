<?php

namespace App\Modules\RisksAndComplaints\Policies;

use App\Models\User;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;

class OperationalRiskPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('risks.view');
    }

    public function view(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.view') && $this->sameTenant($actor, $risk);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('risks.create');
    }

    public function update(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.update') && $this->sameTenant($actor, $risk);
    }

    public function assess(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.assess') && $this->sameTenant($actor, $risk);
    }

    public function approveAssessment(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.approve_assessment') && $this->sameTenant($actor, $risk);
    }

    public function manageTreatment(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.manage_treatment') && $this->sameTenant($actor, $risk);
    }

    public function assign(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.assign') && $this->sameTenant($actor, $risk);
    }

    public function close(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.close') && $this->sameTenant($actor, $risk);
    }

    public function delete(User $actor, OperationalRisk $risk): bool
    {
        return $actor->hasPermission('risks.delete') && $this->sameTenant($actor, $risk);
    }

    private function sameTenant(User $actor, OperationalRisk $risk): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $risk->tenant_id;
    }
}
