<?php

namespace App\Modules\Documents\Policies;

use App\Models\User;
use App\Modules\Documents\Models\DocumentCategory;

class DocumentCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('documents.view')
            || $actor->hasPermission('documents.manage_categories');
    }

    public function view(User $actor, DocumentCategory $category): bool
    {
        return $this->viewAny($actor) && $this->sameTenant($actor, $category);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('documents.manage_categories');
    }

    public function update(User $actor, DocumentCategory $category): bool
    {
        return $actor->hasPermission('documents.manage_categories')
            && $this->sameTenant($actor, $category);
    }

    public function delete(User $actor, DocumentCategory $category): bool
    {
        return $actor->hasPermission('documents.manage_categories')
            && $this->sameTenant($actor, $category);
    }

    private function sameTenant(User $actor, DocumentCategory $category): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }
}
