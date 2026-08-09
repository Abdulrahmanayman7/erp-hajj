<?php

namespace App\Modules\Contracts\Policies;

use App\Models\User;
use App\Modules\Contracts\Models\Contract;

class ContractPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('contracts.view');
    }

    public function view(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('contracts.create');
    }

    public function update(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function delete(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function review(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.review')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function approve(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.approve')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function sign(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.sign')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function execute(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.execute')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function close(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.close')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function renew(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.renew')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }

    public function cancel(User $actor, Contract $contract): bool
    {
        return $actor->hasPermission('contracts.cancel')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $contract->tenant_id;
    }
}
