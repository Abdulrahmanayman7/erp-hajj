<?php

namespace App\Modules\Transportation\Actions\Timeline;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T6 / I8: appends an `event_corrected` entry superseding one
 * lifecycle / outcome event with a new occurred_at, and updates the
 * entity's matching timestamp. The original entry is never modified; each
 * entry can be superseded once (the latest correction can be corrected).
 */
final class CorrectTimelineEvent
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportTimelineEvent $event, string $occurredAt, string $reason, Request $request): TransportTimelineEvent
    {
        return DB::transaction(function () use ($actor, $event, $occurredAt, $reason, $request): TransportTimelineEvent {
            /** @var TransportMovement $movement */
            $movement = TransportMovement::query()->whereKey($event->transport_movement_id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            if ($event->vehicle_run_id !== null) {
                TransportVehicleRun::query()->whereKey($event->vehicle_run_id)->lockForUpdate()->firstOrFail();
            }

            /** @var TransportTimelineEvent $locked */
            $locked = TransportTimelineEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $rootType = $locked->event_type === TimelineEventType::EventCorrected
                ? TimelineEventType::tryFrom((string) ($locked->context['target_event_type'] ?? ''))
                : $locked->event_type;

            $target = $rootType?->correctableTarget();
            if ($rootType === null || $target === null) {
                throw TransportationDomainException::timelineEventNotCorrectable();
            }

            if ($locked->supersededBy()->exists()) {
                throw TransportationDomainException::timelineEventAlreadyCorrected();
            }

            $at = Carbon::parse($occurredAt)->setTimezone(Carbon::now()->getTimezone());
            if ($at->greaterThan(Carbon::now())) {
                throw TransportationDomainException::occurredAtFuture();
            }

            try {
                $correction = $this->timeline->record(
                    seasonId: $locked->season_id,
                    movementId: $locked->transport_movement_id,
                    type: TimelineEventType::EventCorrected,
                    occurredAt: $at,
                    actorId: $actor->id,
                    runId: $locked->vehicle_run_id,
                    passengerId: $locked->run_passenger_id,
                    reason: $reason,
                    context: [
                        'target_event_type' => $rootType->value,
                        'corrected_event_id' => $locked->id,
                        'previous_occurred_at' => $locked->occurred_at->toIso8601String(),
                    ],
                    supersedesEventId: $locked->id,
                );
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::timelineEventAlreadyCorrected();
            }

            [$entity, $column] = $target;
            match ($entity) {
                'movement' => $movement->update([$column => $at]),
                'run' => TransportVehicleRun::query()->whereKey($locked->vehicle_run_id)->firstOrFail()->update([$column => $at]),
                'passenger' => TransportRunPassenger::query()->whereKey($locked->run_passenger_id)->firstOrFail()->update([$column => $at]),
            };

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_TIMELINE_EVENT_CORRECTED, $actor->id, 'transport_timeline_event', $locked->id, $movement->movement_number, $rootType->value, [
                'reason' => $reason,
                'before_values' => ['occurred_at' => $locked->occurred_at->toIso8601String()],
                'after_values' => ['occurred_at' => $at->toIso8601String()],
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'correction_event_id' => $correction->id,
                    'target_event_type' => $rootType->value,
                    'vehicle_run_id' => $locked->vehicle_run_id,
                    'run_passenger_id' => $locked->run_passenger_id,
                ],
            ], $request);

            return $correction;
        });
    }
}
