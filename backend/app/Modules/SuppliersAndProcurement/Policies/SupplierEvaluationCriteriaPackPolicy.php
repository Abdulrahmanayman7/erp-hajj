<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;

class SupplierEvaluationCriteriaPackPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('supplier_evaluations.view')
            || $actor->hasPermission('supplier_evaluations.manage_criteria');
    }

    public function view(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        if (! $this->sameTenant($actor, $pack)) {
            return false;
        }

        if ($actor->hasPermission('supplier_evaluations.manage_criteria')) {
            return true;
        }

        return $actor->hasPermission('supplier_evaluations.view')
            && $pack->status === CriteriaPackStatus::Published;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('supplier_evaluations.manage_criteria');
    }

    public function update(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        return $actor->hasPermission('supplier_evaluations.manage_criteria')
            && $this->sameTenant($actor, $pack);
    }

    public function manageCriteria(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        return $this->update($actor, $pack);
    }

    public function newDraft(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        return $this->update($actor, $pack);
    }

    /**
     * Sensitive: explicit assignment only. SoD preparer/editors≠publisher is enforced in the Action.
     */
    public function publish(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        return $actor->hasPermission('supplier_evaluations.approve_criteria')
            && $this->sameTenant($actor, $pack);
    }

    private function sameTenant(User $actor, SupplierEvaluationCriteriaPack $pack): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $pack->tenant_id;
    }
}
