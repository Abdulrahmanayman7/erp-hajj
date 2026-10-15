<?php

namespace App\Modules\Transportation\Support;

use App\Core\Shared\CorrelationId;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use Illuminate\Support\Carbon;

/**
 * Appends operational timeline entries (ADR-0018 T6). Never updates.
 */
final class TimelineRecorder
{
    public function __construct(private readonly CorrelationId $correlationId) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        int $seasonId,
        int $movementId,
        TimelineEventType $type,
        ?Carbon $occurredAt = null,
        ?int $actorId = null,
        ?int $runId = null,
        ?int $passengerId = null,
        ?string $reason = null,
        array $context = [],
        ?int $supersedesEventId = null,
        ?string $correlationId = null,
    ): TransportTimelineEvent {
        $now = Carbon::now();

        return TransportTimelineEvent::query()->create([
            'season_id' => $seasonId,
            'transport_movement_id' => $movementId,
            'vehicle_run_id' => $runId,
            'run_passenger_id' => $passengerId,
            'event_type' => $type,
            'occurred_at' => $occurredAt ?? $now,
            'recorded_at' => $now,
            'actor_user_id' => $actorId,
            'reason' => $reason,
            'context' => $context === [] ? null : $context,
            'supersedes_event_id' => $supersedesEventId,
            'correlation_id' => $correlationId ?? $this->correlationId->get(),
        ]);
    }
}
