<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\Notification;

final class ShowNotification
{
    public function execute(Notification $notification): Notification
    {
        return $notification;
    }
}
