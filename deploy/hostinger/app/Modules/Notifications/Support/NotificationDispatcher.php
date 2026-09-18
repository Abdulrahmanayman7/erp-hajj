<?php

namespace App\Modules\Notifications\Support;

use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Jobs\DispatchNotificationsJob;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotificationDispatcher
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationComposer $composer,
        private readonly CorrelationId $correlationId,
    ) {}

    /**
     * @param  iterable<int|User|null>  $recipientUsers
     * @param  array<string, scalar|null>  $context
     */
    public function notify(
        NotificationType $type,
        iterable $recipientUsers,
        ?string $title = null,
        ?string $body = null,
        ?NotificationSeverity $severity = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $dedupeBucket = null,
        ?string $occurrenceKey = null,
        array $context = [],
        bool $allowQueue = true,
    ): void {
        $run = function () use (
            $type,
            $recipientUsers,
            $title,
            $body,
            $severity,
            $entityType,
            $entityId,
            $dedupeBucket,
            $occurrenceKey,
            $context,
            $allowQueue,
        ): void {
            try {
                $this->dispatchNow(
                    $type,
                    $recipientUsers,
                    $title,
                    $body,
                    $severity,
                    $entityType,
                    $entityId,
                    $dedupeBucket,
                    $occurrenceKey,
                    $context,
                    $allowQueue,
                );
            } catch (Throwable $e) {
                Log::warning('Notification dispatch failed.', [
                    'type' => $type->value,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);

            return;
        }

        $run();
    }

    /**
     * @param  iterable<int|User|null>  $recipientUsers
     * @param  array<string, scalar|null>  $context
     */
    private function dispatchNow(
        NotificationType $type,
        iterable $recipientUsers,
        ?string $title,
        ?string $body,
        ?NotificationSeverity $severity,
        ?string $entityType,
        ?int $entityId,
        ?string $dedupeBucket,
        ?string $occurrenceKey,
        array $context,
        bool $allowQueue,
    ): void {
        $this->tenantContext->require();

        $users = $this->recipients->filterActiveUsers($recipientUsers);
        if ($users === []) {
            return;
        }

        $severity ??= $type->defaultSeverity();
        $composed = ($title === null || $title === '')
            ? $this->composer->compose($type, $context)
            : ['title' => mb_substr($title, 0, 255), 'body' => $body !== null ? mb_substr($body, 0, 1000) : null];

        if ($body !== null && $title !== null && $title !== '') {
            $composed['body'] = mb_substr($body, 0, 1000);
        }

        $max = max(1, (int) config('notifications.sync_fanout_max', 20));

        if ($allowQueue && count($users) > $max) {
            DispatchNotificationsJob::dispatch(
                type: $type,
                recipientUserIds: array_map(static fn (User $u): int => (int) $u->id, $users),
                title: $composed['title'],
                body: $composed['body'],
                severity: $severity,
                entityType: $entityType,
                entityId: $entityId,
                dedupeBucket: $dedupeBucket,
                occurrenceKey: $occurrenceKey,
                correlationId: $this->correlationId->get(),
            );

            return;
        }

        foreach ($users as $user) {
            $this->persistOne(
                $type,
                (int) $user->id,
                $composed['title'],
                $composed['body'],
                $severity,
                $entityType,
                $entityId,
                $dedupeBucket,
                $occurrenceKey,
                $this->correlationId->get(),
            );
        }
    }

    /**
     * Persist without re-queueing (used by DispatchNotificationsJob).
     *
     * @param  list<int>  $recipientUserIds
     */
    public function persistForRecipients(
        NotificationType $type,
        array $recipientUserIds,
        string $title,
        ?string $body,
        NotificationSeverity $severity,
        ?string $entityType,
        ?int $entityId,
        ?string $dedupeBucket,
        ?string $occurrenceKey,
        ?string $correlationId,
    ): void {
        $users = $this->recipients->filterActiveUsers($recipientUserIds);
        foreach ($users as $user) {
            $this->persistOne(
                $type,
                (int) $user->id,
                $title,
                $body,
                $severity,
                $entityType,
                $entityId,
                $dedupeBucket,
                $occurrenceKey,
                $correlationId,
            );
        }
    }

    private function persistOne(
        NotificationType $type,
        int $recipientUserId,
        string $title,
        ?string $body,
        NotificationSeverity $severity,
        ?string $entityType,
        ?int $entityId,
        ?string $dedupeBucket,
        ?string $occurrenceKey,
        ?string $correlationId,
    ): void {
        $bucket = $occurrenceKey ?? $dedupeBucket;
        $dedupeKey = $bucket !== null && $bucket !== ''
            ? NotificationDedupe::buildKey($type, $entityType, $entityId, $recipientUserId, $bucket)
            : null;

        try {
            $notification = new Notification;
            $notification->forceFill([
                'recipient_user_id' => $recipientUserId,
                'type' => $type,
                'title' => mb_substr($title, 0, 255),
                'body' => $body !== null ? mb_substr($body, 0, 1000) : null,
                'severity' => $severity,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'dedupe_key' => $dedupeKey,
                'read_at' => null,
                'correlation_id' => $correlationId,
            ]);
            $notification->save();
        } catch (UniqueConstraintViolationException) {
            // Idempotent: same (tenant_id, dedupe_key) already exists.
        } catch (Throwable $e) {
            Log::warning('Notification insert failed.', [
                'type' => $type->value,
                'recipient_user_id' => $recipientUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
