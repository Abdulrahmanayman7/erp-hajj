<?php

namespace App\Modules\Notifications\Policies;

use App\Models\User;
use App\Modules\Notifications\Models\Notification;

class NotificationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->isActive();
    }

    public function view(User $actor, Notification $notification): bool
    {
        return $this->owns($actor, $notification);
    }

    public function markRead(User $actor, Notification $notification): bool
    {
        return $this->owns($actor, $notification);
    }

    public function markReadAll(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->isActive();
    }

    private function owns(User $actor, Notification $notification): bool
    {
        if ($actor->tenant_id === null || ! $actor->isActive()) {
            return false;
        }

        return (int) $actor->tenant_id === (int) $notification->tenant_id
            && (int) $actor->id === (int) $notification->recipient_user_id;
    }
}
