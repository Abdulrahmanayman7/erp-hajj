<?php

namespace App\Modules\Decisions\Policies;

use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;

class DecisionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('decisions.view');
    }

    public function view(User $actor, Decision $decision): bool
    {
        return $actor->hasPermission('decisions.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $decision->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('decisions.create');
    }

    public function update(User $actor, Decision $decision): bool
    {
        return $actor->hasPermission('decisions.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $decision->tenant_id;
    }

    public function approve(User $actor, Decision $decision): bool
    {
        return $actor->hasPermission('decisions.approve')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $decision->tenant_id
            && $decision->status === DecisionStatus::PendingApproval;
    }

    public function close(User $actor, Decision $decision): bool
    {
        return $actor->hasPermission('decisions.close')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $decision->tenant_id
            && $decision->status === DecisionStatus::Approved;
    }

    public function delete(User $actor, Decision $decision): bool
    {
        return $actor->hasPermission('decisions.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $decision->tenant_id
            && $decision->status === DecisionStatus::Draft;
    }
}
