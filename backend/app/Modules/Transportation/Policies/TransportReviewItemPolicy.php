<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportReviewItem;

class TransportReviewItemPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function resolve(User $actor, TransportReviewItem $item): bool
    {
        return $this->allows($actor, 'transportation.resolve_reviews', $item);
    }
}
