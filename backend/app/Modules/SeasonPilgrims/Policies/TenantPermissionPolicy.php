<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared check for this module's policies: the permission AND (for record
 * checks) same-tenant ownership. No persona bypass, Tenant Owner included.
 * Being a pilgrim-group supervisor grants nothing here (F27).
 */
abstract class TenantPermissionPolicy
{
    protected function allows(User $actor, string $permission, ?Model $record = null): bool
    {
        if (! $actor->hasPermission($permission)) {
            return false;
        }

        if ($record === null) {
            return true;
        }

        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $record->getAttribute('tenant_id');
    }
}
