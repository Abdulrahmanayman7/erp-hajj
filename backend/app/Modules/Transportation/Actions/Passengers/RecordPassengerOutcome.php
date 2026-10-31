<?php

namespace App\Modules\Transportation\Actions\Passengers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\OccurredAtResolver;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T8 (Active season only), all-or-nothing per request:
 *   board:   Assigned → Boarded   (Run Boarding)
 *   no-show: Assigned → No-show   (Run Boarding / Departed / Arrived)
 *   arrive:  Boarded  → Arrived   (Run Arrived)
 */
final class RecordPassengerOutcome
{
    public const BOARD = 'board';

    public const NO_SHOW = 'no-show';

    public const ARRIVE = 'arrive';

    public function __construct(
        private readonly OccurredAtResolver $occurredAt,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  list<int>  $passengerIds
     */
    public function execute(User $actor, TransportVehicleRun $run, string $outcome, array $passengerIds, ?string $occurredAt, ?string $reason, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $outcome, $passengerIds, $occurredAt, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertActive($movement->season()->firstOrFail());

            [$runStatuses, $from, $to, $column, $eventType, $auditEvent] = match ($outcome) {
                self::BOARD => [[RunStatus::Boarding], PassengerStatus::Assigned, PassengerStatus::Boarded, 'boarded_at', TimelineEventType::PassengerBoarded, AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_BOARDED],
                self::NO_SHOW => [[RunStatus::Boarding, RunStatus::Departed, RunStatus::Arrived], PassengerStatus::Assigned, PassengerStatus::NoShow, 'no_show_at', TimelineEventType::PassengerNoShow, AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_NO_SHOW],
                self::ARRIVE => [[RunStatus::Arrived], PassengerStatus::Boarded, PassengerStatus::Arrived, 'arrived_at', TimelineEventType::PassengerArrived, AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ARRIVED],
            };

            if (! in_array($locked->status, $runStatuses, true)) {
                throw TransportationDomainException::runInvalidTransition();
            }

            $ids = array_values(array_unique(array_map('intval', $passengerIds)));
            sort($ids);

            $passengers = TransportRunPassenger::query()
                ->where('vehicle_run_id', $locked->id)
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $invalid = array_values(array_filter($ids, fn (int $id): bool => ! $passengers->has($id) || $passengers->get($id)->status !== $from));
            if ($invalid !== []) {
                throw TransportationDomainException::passengerInvalidTransition($invalid);
            }

            $at = $this->occurredAt->resolve($actor, $occurredAt, $reason);

            foreach ($passengers as $passenger) {
                $passenger->update(['status' => $to, $column => $at->at]);

                $this->timeline->record(
                    seasonId: $locked->season_id,
                    movementId: $movement->id,
                    type: $eventType,
                    occurredAt: $at->at,
                    actorId: $actor->id,
                    runId: $locked->id,
                    passengerId: $passenger->id,
                    reason: $at->reason,
                    context: ['season_pilgrim_id' => $passenger->season_pilgrim_id, 'backdated' => $at->backdated],
                );
            }

            $this->audit->record($auditEvent, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $at->reason,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'passenger_ids' => $ids,
                    'season_pilgrim_ids' => $passengers->pluck('season_pilgrim_id')->values()->all(),
                    'occurred_at' => $at->at->toIso8601String(),
                    'backdated' => $at->backdated,
                ],
            ], $request);

            return $locked;
        });
    }
}
