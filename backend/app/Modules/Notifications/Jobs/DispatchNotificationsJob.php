<?php

namespace App\Modules\Notifications\Jobs;

use App\Core\Tenancy\Jobs\TenantAware;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAware;

    /**
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        public NotificationType $type,
        public array $recipientUserIds,
        public string $title,
        public ?string $body,
        public NotificationSeverity $severity,
        public ?string $entityType,
        public ?int $entityId,
        public ?string $dedupeBucket,
        public ?string $occurrenceKey,
        public ?string $correlationId,
    ) {
        $this->captureTenantContext();
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $dispatcher->persistForRecipients(
            $this->type,
            $this->recipientUserIds,
            $this->title,
            $this->body,
            $this->severity,
            $this->entityType,
            $this->entityId,
            $this->dedupeBucket,
            $this->occurrenceKey,
            $this->correlationId,
        );
    }
}
