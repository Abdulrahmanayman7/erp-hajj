<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportRunPassenger;

class TransportRunPassengerPolicy extends TenantPermissionPolicy
{
    public function cancel(User $actor, TransportRunPassenger $passenger): bool
    {
        return $this->allows($actor, 'transportation.manage_manifest', $passenger);
    }

    public function overrideEntitlement(User $actor, TransportRunPassenger $passenger): bool
    {
        return $this->allows($actor, 'transportation.override_entitlement', $passenger);
    }
}
