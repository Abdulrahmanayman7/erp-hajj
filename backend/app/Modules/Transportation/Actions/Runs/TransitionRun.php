<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\MovementStatus;
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
 * ADR-0018 T6 / I2 / I3 / I5 (Active season only):
 *   start-boarding: Planned → Boarding (Movement In Progress)
 *   depart:         Boarding → Departed (current driver required)
 *   arrive:         Departed → Arrived
 *   complete:       Arrived → Completed (every passenger arrived / no-show / cancelled)
 */
final class TransitionRun
{
    public const START_BOARDING = 'start-boarding';

    public const DEPART = 'depart';

    public const ARRIVE = 'arrive';

    public const COMPLETE = 'complete';

    public function __construct(
        private readonly OccurredAtResolver $occurredAt,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportVehicleRun $run, string $transition, ?string $occurredAt, ?string $reason, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $transition, $occurredAt, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertActive($movement->season()->firstOrFail());

            [$from, $to, $column, $eventType, $auditEvent] = match ($transition) {
                self::START_BOARDING => [RunStatus::Planned, RunStatus::Boarding, 'boarding_started_at', TimelineEventType::RunBoardingStarted, AuthorizationSecurityEvent::TRANSPORT_RUN_BOARDING_STARTED],
                self::DEPART => [RunStatus::Boarding, RunStatus::Departed, 'departed_at', TimelineEventType::RunDeparted, AuthorizationSecurityEvent::TRANSPORT_RUN_DEPARTED],
                self::ARRIVE => [RunStatus::Departed, RunStatus::Arrived, 'arrived_at', TimelineEventType::RunArrived, AuthorizationSecurityEvent::TRANSPORT_RUN_ARRIVED],
                self::COMPLETE => [RunStatus::Arrived, RunStatus::Completed, 'completed_at', TimelineEventType::RunCompleted, AuthorizationSecurityEvent::TRANSPORT_RUN_COMPLETED],
            };

            if ($locked->status !== $from || $movement->status !== MovementStatus::InProgress) {
                throw TransportationDomainException::runInvalidTransition();
            }

            if ($transition === self::DEPART && ! $locked->currentDriverAssignment()->exists()) {
                throw TransportationDomainException::runDriverRequired();
            }

            if ($transition === self::COMPLETE) {
                $unsettled = TransportRunPassenger::query()
                    ->where('vehicle_run_id', $locked->id)
                    ->whereIn('status', [PassengerStatus::Assigned->value, PassengerStatus::Boarded->value])
                    ->exists();

                if ($unsettled) {
                    throw TransportationDomainException::runPassengersNotSettled();
                }
            }

            $at = $this->occurredAt->resolve($actor, $occurredAt, $reason);
            $locked->update(['status' => $to, $column => $at->at]);

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $movement->id,
                type: $eventType,
                occurredAt: $at->at,
                actorId: $actor->id,
                runId: $locked->id,
                reason: $at->reason,
                context: ['from_status' => $from->value, 'backdated' => $at->backdated],
            );

            $this->audit->record($auditEvent, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $at->reason,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'transport_movement_id' => $movement->id,
                    'occurred_at' => $at->at->toIso8601String(),
                    'backdated' => $at->backdated,
                ],
            ], $request);

            return RunLoader::load($locked);
        });
    }
}
