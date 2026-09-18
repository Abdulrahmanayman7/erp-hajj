<?php

namespace App\Modules\OrganizationStructure\Policies;

use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

class OrganizationUnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('organization_units.view');
    }

    public function view(User $actor, OrganizationUnit $unit): bool
    {
        return $actor->hasPermission('organization_units.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $unit->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('organization_units.create');
    }

    public function update(User $actor, OrganizationUnit $unit): bool
    {
        return $actor->hasPermission('organization_units.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $unit->tenant_id;
    }

    public function delete(User $actor, OrganizationUnit $unit): bool
    {
        return $actor->hasPermission('organization_units.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $unit->tenant_id;
    }
}
