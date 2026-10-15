<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use Illuminate\Support\Carbon;

/**
 * Assigned → Cancelled (T8). The row is kept; capacity is released because
 * the occupying flag clears. Callers hold the Run lock.
 */
final class PassengerCancellation
{
    public function __construct(private readonly TimelineRecorder $timeline) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(
        TransportVehicleRun $run,
        TransportRunPassenger $passenger,
        ?int $actorId,
        string $reason,
        array $context = [],
        ?string $correlationId = null,
    ): void {
        $now = Carbon::now();

        $passenger->update([
            'status' => PassengerStatus::Cancelled,
            'cancelled_at' => $now,
            'cancelled_by' => $actorId,
            'cancellation_reason' => $reason,
        ]);

        $this->timeline->record(
            seasonId: $run->season_id,
            movementId: $run->transport_movement_id,
            type: TimelineEventType::PassengerCancelled,
            occurredAt: $now,
            actorId: $actorId,
            runId: $run->id,
            passengerId: $passenger->id,
            reason: $reason,
            context: ['season_pilgrim_id' => $passenger->season_pilgrim_id, ...$context],
            correlationId: $correlationId,
        );
    }
}
