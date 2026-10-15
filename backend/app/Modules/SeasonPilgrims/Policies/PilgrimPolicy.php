<?php

namespace App\Modules\SeasonPilgrims\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Pilgrim;

class PilgrimPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'pilgrims.view');
    }

    public function view(User $actor, Pilgrim $pilgrim): bool
    {
        return $this->allows($actor, 'pilgrims.view', $pilgrim);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'pilgrims.create');
    }

    public function update(User $actor, Pilgrim $pilgrim): bool
    {
        return $this->allows($actor, 'pilgrims.update', $pilgrim);
    }

    public function delete(User $actor, Pilgrim $pilgrim): bool
    {
        return $this->allows($actor, 'pilgrims.delete', $pilgrim);
    }

    public function manageIdentityDocuments(User $actor, Pilgrim $pilgrim): bool
    {
        return $this->allows($actor, 'pilgrims.manage_identity_documents', $pilgrim);
    }
}
