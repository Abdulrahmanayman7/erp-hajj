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
use App\Modules\Transportation\Support\OccurredAt;
use App\Modules\Transportation\Support\OccurredAtResolver;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T6 / I2 / I3:
 *   plan:     Draft → Planned (Draft or Active season)
 *   start:    Planned → In Progress (Active season)
 *   complete: In Progress → Completed (Active season, all Runs terminal)
 */
final class TransitionMovement
{
    public const PLAN = 'plan';

    public const START = 'start';

    public const COMPLETE = 'complete';

    public function __construct(
        private readonly OccurredAtResolver $occurredAt,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportMovement $movement, string $transition, ?string $occurredAt, ?string $reason, Request $request): TransportMovement
    {
        return DB::transaction(function () use ($actor, $movement, $transition, $occurredAt, $reason, $request): TransportMovement {
            /** @var TransportMovement $locked */
            $locked = TransportMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            $season = $locked->season()->firstOrFail();

            [$from, $to, $column, $eventType, $auditEvent] = match ($transition) {
                self::PLAN => [MovementStatus::Draft, MovementStatus::Planned, 'planned_at', TimelineEventType::MovementPlanned, AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_PLANNED],
                self::START => [MovementStatus::Planned, MovementStatus::InProgress, 'started_at', TimelineEventType::MovementStarted, AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_STARTED],
                self::COMPLETE => [MovementStatus::InProgress, MovementStatus::Completed, 'completed_at', TimelineEventType::MovementCompleted, AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_COMPLETED],
            };

            if ($transition === self::PLAN) {
                SeasonGuard::assertWritable($season);
            } else {
                SeasonGuard::assertActive($season);
            }

            if ($locked->status !== $from) {
                throw TransportationDomainException::movementInvalidTransition();
            }

            if ($transition === self::COMPLETE) {
                $unsettled = TransportVehicleRun::query()
                    ->where('transport_movement_id', $locked->id)
                    ->get(['id', 'status'])
                    ->contains(fn (TransportVehicleRun $run): bool => ! $run->status->isTerminal());

                if ($unsettled) {
                    throw TransportationDomainException::movementRunsNotSettled();
                }
            }

            $at = $transition === self::PLAN
                ? new OccurredAt(now(), false, null)
                : $this->occurredAt->resolve($actor, $occurredAt, $reason);

            $locked->update(['status' => $to, $column => $at->at]);

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $locked->id,
                type: $eventType,
                occurredAt: $at->at,
                actorId: $actor->id,
                reason: $at->reason,
                context: ['from_status' => $from->value, 'backdated' => $at->backdated],
            );

            $this->audit->record($auditEvent, $actor->id, 'transport_movement', $locked->id, $locked->movement_number, null, [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $at->reason,
                'metadata' => ['season_id' => $locked->season_id, 'occurred_at' => $at->at->toIso8601String(), 'backdated' => $at->backdated],
            ], $request);

            return MovementLoader::load($locked);
        });
    }
}
