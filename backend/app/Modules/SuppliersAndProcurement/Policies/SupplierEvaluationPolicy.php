<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;

class SupplierEvaluationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('supplier_evaluations.view')
            || $actor->hasPermission('supplier_evaluations.prepare')
            || $actor->hasPermission('supplier_evaluations.approve');
    }

    public function view(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $this->sameTenant($actor, $evaluation)
            && (
                $actor->hasPermission('supplier_evaluations.view')
                || $actor->hasPermission('supplier_evaluations.prepare')
                || $actor->hasPermission('supplier_evaluations.approve')
                || $actor->hasPermission('supplier_evaluations.handle_appeal')
            );
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('supplier_evaluations.prepare');
    }

    public function update(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $actor->hasPermission('supplier_evaluations.prepare')
            && $this->sameTenant($actor, $evaluation);
    }

    public function submit(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $this->update($actor, $evaluation);
    }

    public function approve(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $actor->hasPermission('supplier_evaluations.approve')
            && $this->sameTenant($actor, $evaluation);
    }

    public function reject(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $this->approve($actor, $evaluation);
    }

    public function fileAppeal(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $this->sameTenant($actor, $evaluation)
            && (
                $actor->hasPermission('supplier_evaluations.prepare')
                || $actor->hasPermission('supplier_evaluations.handle_appeal')
            );
    }

    public function viewAppeals(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $this->view($actor, $evaluation);
    }

    private function sameTenant(User $actor, SupplierEvaluation $evaluation): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $evaluation->tenant_id;
    }
}
