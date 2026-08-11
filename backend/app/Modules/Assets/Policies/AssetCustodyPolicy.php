<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Support\AssetReferenceValidator;

class AssetCustodyPolicy
{
    public function __construct(
        private readonly AssetReferenceValidator $references,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('assets.view');
    }

    public function view(User $actor, AssetCustody $custody): bool
    {
        if (! $this->sameTenant($actor, $custody)) {
            return false;
        }

        return $actor->hasPermission('assets.view')
            || $this->references->isCustodyHolderSelf($actor, $custody);
    }

    private function sameTenant(User $actor, AssetCustody $custody): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $custody->tenant_id;
    }
}
