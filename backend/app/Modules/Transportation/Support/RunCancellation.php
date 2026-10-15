<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use Illuminate\Support\Carbon;

/**
 * Planned | Boarding → Cancelled (T6, I4). Assigned passengers are
 * cancelled; a Run with Boarded passengers cannot be cancelled. Resource
 * assignments are kept as history. Callers hold the Movement and Run locks.
 */
final class RunCancellation
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly PassengerCancellation $passengers,
    ) {}

    /**
     * @return int number of cancelled passengers
     */
    public function cancel(TransportVehicleRun $run, ?int $actorId, string $reason): int
    {
        if (! in_array($run->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
            throw TransportationDomainException::runInvalidTransition();
        }

        $passengers = TransportRunPassenger::query()
            ->where('vehicle_run_id', $run->id)
            ->whereIn('status', [PassengerStatus::Assigned->value, PassengerStatus::Boarded->value])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($passengers->contains(fn (TransportRunPassenger $p): bool => $p->status === PassengerStatus::Boarded)) {
            throw TransportationDomainException::runHasBoardedPassengers();
        }

        foreach ($passengers as $passenger) {
            $this->passengers->cancel($run, $passenger, $actorId, $reason, ['cause' => 'run_cancelled']);
        }

        $previous = $run->status;
        $now = Carbon::now();
        $run->update([
            'status' => RunStatus::Cancelled,
            'cancelled_at' => $now,
            'cancellation_reason' => $reason,
        ]);

        $this->timeline->record(
            seasonId: $run->season_id,
            movementId: $run->transport_movement_id,
            type: TimelineEventType::RunCancelled,
            occurredAt: $now,
            actorId: $actorId,
            runId: $run->id,
            reason: $reason,
            context: ['from_status' => $previous->value, 'cancelled_passengers' => $passengers->count()],
        );

        return $passengers->count();
    }
}
