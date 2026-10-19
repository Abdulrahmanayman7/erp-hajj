<?php

namespace App\Modules\Accommodation\Support;

use App\Core\Shared\CorrelationId;
use App\Modules\Accommodation\Models\AccommodationEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * Append-only operational timeline writes with tenant-scoped idempotency.
 */
final class AccommodationEventWriter
{
    public function __construct(private readonly CorrelationId $correlationId) {}

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{0: AccommodationEvent, 1: bool} event + whether newly inserted
     */
    public function appendOnce(
        int $seasonId,
        int $assignmentId,
        string $eventType,
        CarbonInterface $occurredAt,
        ?int $actorUserId,
        string $source,
        string $idempotencyKey,
        ?array $meta = null,
    ): array {
        $existing = AccommodationEvent::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $event = new AccommodationEvent([
                'season_id' => $seasonId,
                'assignment_id' => $assignmentId,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
                'recorded_at' => now('UTC'),
                'actor_user_id' => $actorUserId,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $this->correlationId->get(),
                'meta' => $meta,
            ]);
            AccommodationPersistence::save(fn () => $event->save());

            return [$event, true];
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000' && ! str_contains($e->getMessage(), 'Duplicate')) {
                throw $e;
            }

            $existing = AccommodationEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return [$existing, false];
        }
    }
}
