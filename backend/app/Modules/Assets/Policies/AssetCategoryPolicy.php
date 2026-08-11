<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Assets\Models\AssetCategory;

class AssetCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('assets.view');
    }

    public function view(User $actor, AssetCategory $category): bool
    {
        return $actor->hasPermission('assets.view')
            && $this->sameTenant($actor, $category);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('assets.update');
    }

    public function update(User $actor, AssetCategory $category): bool
    {
        return $actor->hasPermission('assets.update')
            && $this->sameTenant($actor, $category);
    }

    public function delete(User $actor, AssetCategory $category): bool
    {
        return $actor->hasPermission('assets.update')
            && $this->sameTenant($actor, $category);
    }

    private function sameTenant(User $actor, AssetCategory $category): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }
}
