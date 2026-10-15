<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportTimelineEvent;

class TransportTimelineEventPolicy extends TenantPermissionPolicy
{
    public function correct(User $actor, TransportTimelineEvent $event): bool
    {
        return $this->allows($actor, 'transportation.correct_history', $event);
    }
}
