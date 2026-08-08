<?php

namespace App\Core\Auth\Listeners;

use App\Core\Auth\Events\AuthSecurityEvent;
use Illuminate\Support\Facades\Log;

/**
 * Temporary sink until the Audit module persists these events.
 * Never logs secrets — callers must already sanitize context.
 */
class LogAuthSecurityEvent
{
    public function handle(AuthSecurityEvent $event): void
    {
        Log::info('auth.security_event', [
            'event' => $event->name,
            ...$event->context,
        ]);
    }
}
