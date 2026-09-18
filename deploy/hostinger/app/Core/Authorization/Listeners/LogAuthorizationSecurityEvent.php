<?php

namespace App\Core\Authorization\Listeners;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use Illuminate\Support\Facades\Log;

final class LogAuthorizationSecurityEvent
{
    public function handle(AuthorizationSecurityEvent $event): void
    {
        Log::info('authorization.security_event', [
            'event' => $event->name,
            ...$event->context,
        ]);
    }
}
