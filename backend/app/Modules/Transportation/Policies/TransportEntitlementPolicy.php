<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportEntitlement;

class TransportEntitlementPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportEntitlement $entitlement): bool
    {
        return $this->allows($actor, 'transportation.view', $entitlement);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_entitlements');
    }

    public function update(User $actor, TransportEntitlement $entitlement): bool
    {
        return $this->allows($actor, 'transportation.manage_entitlements', $entitlement);
    }

    public function delete(User $actor, TransportEntitlement $entitlement): bool
    {
        return $this->allows($actor, 'transportation.manage_entitlements', $entitlement);
    }
}
