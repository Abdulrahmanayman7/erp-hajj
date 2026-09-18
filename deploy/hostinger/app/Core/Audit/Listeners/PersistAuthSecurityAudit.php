<?php

namespace App\Core\Audit\Listeners;

use App\Core\Audit\AuditRecorder;
use App\Core\Auth\Events\AuthSecurityEvent;

final class PersistAuthSecurityAudit
{
    public function __construct(
        private readonly AuditRecorder $recorder,
    ) {}

    public function handle(AuthSecurityEvent $event): void
    {
        $this->recorder->recordAuthEvent($event->name, $event->context);
    }
}
