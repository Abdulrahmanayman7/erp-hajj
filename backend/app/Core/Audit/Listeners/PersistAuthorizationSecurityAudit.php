<?php

namespace App\Core\Audit\Listeners;

use App\Core\Audit\AuditRecorder;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;

final class PersistAuthorizationSecurityAudit
{
    public function __construct(
        private readonly AuditRecorder $recorder,
    ) {}

    public function handle(AuthorizationSecurityEvent $event): void
    {
        $this->recorder->recordAuthorizationEvent($event->name, $event->context);
    }
}
