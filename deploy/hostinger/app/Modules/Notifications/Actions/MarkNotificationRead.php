<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\Notification;

final class MarkNotificationRead
{
    public function execute(Notification $notification): Notification
    {
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification->fresh() ?? $notification;
    }
}
