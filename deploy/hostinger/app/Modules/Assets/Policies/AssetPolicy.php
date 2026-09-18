<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Support\AssetReferenceValidator;

class AssetPolicy
{
    public function __construct(
        private readonly AssetReferenceValidator $references,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('assets.view');
    }

    public function view(User $actor, Asset $asset): bool
    {
        if (! $this->sameTenant($actor, $asset)) {
            return false;
        }

        return $actor->hasPermission('assets.view')
            || $this->references->isAssetHolderSelf($actor, $asset);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('assets.create');
    }

    public function update(User $actor, Asset $asset): bool
    {
        return $actor->hasPermission('assets.update')
            && $this->sameTenant($actor, $asset);
    }

    public function delete(User $actor, Asset $asset): bool
    {
        return $actor->hasPermission('assets.delete')
            && $this->sameTenant($actor, $asset);
    }

    public function assign(User $actor, Asset $asset): bool
    {
        return $actor->hasPermission('assets.assign')
            && $this->sameTenant($actor, $asset);
    }

    public function returnCustody(User $actor, Asset $asset): bool
    {
        return $actor->hasPermission('assets.return')
            && $this->sameTenant($actor, $asset);
    }

    public function retire(User $actor, Asset $asset): bool
    {
        return $actor->hasPermission('assets.retire')
            && $this->sameTenant($actor, $asset);
    }

    private function sameTenant(User $actor, Asset $asset): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $asset->tenant_id;
    }
}
