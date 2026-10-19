<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Support\AccommodationAccess;

trait AuthorizesAccommodation
{
    protected function verb(User $actor, string $permission): bool
    {
        return $actor->tenant_id !== null && $actor->hasPermission($permission);
    }

    protected function canRead(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.view') || $this->verb($actor, 'accommodation.draft');
    }

    protected function seesFacility(User $actor, int $facilityId): bool
    {
        return $this->canRead($actor) && app(AccommodationAccess::class)->canSeeFacility($actor, $facilityId);
    }
}
