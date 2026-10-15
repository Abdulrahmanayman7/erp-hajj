<?php

namespace App\Modules\Transportation\Listeners;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Events\SeasonPilgrimCancelled;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\ReviewSeverity;
use App\Modules\Transportation\Enums\ReviewStatus;
use App\Modules\Transportation\Enums\ReviewType;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\PassengerCancellation;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T8 / T10 (BUSINESS_RULES Â§14). Idempotent, keyed by passenger
 * row: a re-delivered event finds rows already cancelled or reviews already
 * raised. Never deletes Transportation rows and never writes the foundation.
 */
final class HandleSeasonPilgrimCancelled
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PassengerCancellation $cancellation,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function handle(SeasonPilgrimCancelled $event): void
    {
        $tenant = Tenant::query()->find($event->tenantId);
        if ($tenant === null) {
            return;
        }

        $this->tenantContext->runAsTenant($tenant, function () use ($event): void {
            $runIds = TransportRunPassenger::query()
                ->where('season_id', $event->seasonId)
                ->where('season_pilgrim_id', $event->seasonPilgrimId)
                ->whereIn('status', [PassengerStatus::Assigned->value, PassengerStatus::Boarded->value, PassengerStatus::NoShow->value])
                ->orderBy('vehicle_run_id')
                ->distinct()
                ->pluck('vehicle_run_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($runIds as $runId) {
                DB::transaction(fn () => $this->handleRun($runId, $event));
            }
        });
    }

    private function handleRun(int $runId, SeasonPilgrimCancelled $event): void
    {
        [$movement, $run] = RunLocks::lock($runId);

        $passengers = TransportRunPassenger::query()
            ->where('vehicle_run_id', $run->id)
            ->where('season_pilgrim_id', $event->seasonPilgrimId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($passengers as $passenger) {
            $runOpen = in_array($run->status, [RunStatus::Planned, RunStatus::Boarding], true);

            if ($passenger->status === PassengerStatus::Assigned && $runOpen) {
                $this->cancellation->cancel($run, $passenger, $event->cancelledBy, $event->reason, ['cause' => 'season_pilgrim_cancelled'], $event->correlationId);

                $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_CANCELLED, $event->cancelledBy, 'transport_run_passenger', $passenger->id, $movement->movement_number.'/'.$run->run_number, null, [
                    'from_status' => PassengerStatus::Assigned->value,
                    'to_status' => PassengerStatus::Cancelled->value,
                    'reason' => $event->reason,
                    'metadata' => [
                        'season_id' => $run->season_id,
                        'vehicle_run_id' => $run->id,
                        'season_pilgrim_id' => $event->seasonPilgrimId,
                        'cause' => 'season_pilgrim_cancelled',
                        'correlation_id' => $event->correlationId,
                    ],
                ]);

                continue;
            }

            [$type, $severity] = match (true) {
                $passenger->status === PassengerStatus::Boarded => [ReviewType::CancelledOnBoard, ReviewSeverity::Critical],
                in_array($passenger->status, [PassengerStatus::Assigned, PassengerStatus::NoShow], true) && ! $runOpen => [ReviewType::CancelledAfterDeparture, ReviewSeverity::NonCritical],
                default => [null, null],
            };

            if ($type === null) {
                continue;
            }

            $this->raiseReview($run, $movement->movement_number, $passenger, $type, $severity, $event);
        }
    }

    private function raiseReview(TransportVehicleRun $run, string $movementNumber, TransportRunPassenger $passenger, ReviewType $type, ReviewSeverity $severity, SeasonPilgrimCancelled $event): void
    {
        $exists = TransportReviewItem::query()
            ->where('run_passenger_id', $passenger->id)
            ->where('review_type', $type->value)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            $item = DB::transaction(fn (): TransportReviewItem => TransportReviewItem::query()->create([
                'season_id' => $run->season_id,
                'vehicle_run_id' => $run->id,
                'run_passenger_id' => $passenger->id,
                'season_pilgrim_id' => $passenger->season_pilgrim_id,
                'review_type' => $type,
                'severity' => $severity,
                'status' => ReviewStatus::Open,
                'details' => [
                    'passenger_status' => $passenger->status->value,
                    'run_status' => $run->status->value,
                    'cancellation_reason' => $event->reason,
                    'cancelled_at' => $event->cancelledAt,
                ],
                'raised_at' => Carbon::now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $this->timeline->record(
            seasonId: $run->season_id,
            movementId: $run->transport_movement_id,
            type: TimelineEventType::ReviewRaised,
            actorId: $event->cancelledBy,
            runId: $run->id,
            passengerId: $passenger->id,
            reason: $event->reason,
            context: ['review_item_id' => $item->id, 'review_type' => $type->value, 'severity' => $severity->value],
            correlationId: $event->correlationId,
        );

        $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_REVIEW_RAISED, $event->cancelledBy, 'transport_review_item', $item->id, $movementNumber.'/'.$run->run_number, $type->value, [
            'reason' => $event->reason,
            'metadata' => [
                'season_id' => $run->season_id,
                'severity' => $severity->value,
                'season_pilgrim_id' => $passenger->season_pilgrim_id,
                'run_passenger_id' => $passenger->id,
                'correlation_id' => $event->correlationId,
            ],
        ]);
    }
}
