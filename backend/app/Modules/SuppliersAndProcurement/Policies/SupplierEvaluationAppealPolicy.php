<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;

class SupplierEvaluationAppealPolicy
{
    public function accept(User $actor, SupplierEvaluationAppeal $appeal): bool
    {
        return $actor->hasPermission('supplier_evaluations.handle_appeal')
            && $this->sameTenant($actor, $appeal);
    }

    public function reject(User $actor, SupplierEvaluationAppeal $appeal): bool
    {
        return $this->accept($actor, $appeal);
    }

    private function sameTenant(User $actor, SupplierEvaluationAppeal $appeal): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $appeal->tenant_id;
    }
}
