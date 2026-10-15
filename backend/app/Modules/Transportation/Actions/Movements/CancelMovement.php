<?php

namespace App\Modules\Transportation\Actions\Movements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\MovementStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\RunCancellation;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportTransaction;
use Illuminate\Http\Request;

/**
 * Draft | Planned → Cancelled (T6, I4). Its Runs (all still Planned) are
 * cancelled together with their Assigned passengers; nothing is deleted.
 */
final class CancelMovement
{
    public function __construct(
        private readonly RunCancellation $runs,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportMovement $movement, string $reason, Request $request): TransportMovement
    {
        return TransportTransaction::run(function () use ($actor, $movement, $reason, $request): TransportMovement {
            /** @var TransportMovement $locked */
            $locked = TransportMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if (! $locked->status->isEditable()) {
                throw TransportationDomainException::movementInvalidTransition();
            }

            $runs = TransportVehicleRun::query()
                ->where('transport_movement_id', $locked->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (TransportVehicleRun $run): bool => ! $run->status->isTerminal());

            $cancelledPassengers = 0;
            foreach ($runs as $run) {
                $cancelledPassengers += $this->runs->cancel($run, $actor->id, $reason);
            }

            $from = $locked->status;
            $locked->update([
                'status' => MovementStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $locked->id,
                type: TimelineEventType::MovementCancelled,
                actorId: $actor->id,
                reason: $reason,
                context: ['from_status' => $from->value, 'cancelled_runs' => $runs->count(), 'cancelled_passengers' => $cancelledPassengers],
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_CANCELLED, $actor->id, 'transport_movement', $locked->id, $locked->movement_number, null, [
                'from_status' => $from->value,
                'to_status' => MovementStatus::Cancelled->value,
                'reason' => $reason,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'cancelled_run_ids' => $runs->pluck('id')->values()->all(),
                    'cancelled_passengers' => $cancelledPassengers,
                ],
            ], $request);

            return MovementLoader::load($locked);
        });
    }
}
