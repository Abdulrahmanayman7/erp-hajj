<?php

namespace App\Modules\Transportation\Actions\Reviews;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\ReviewStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closes a review item with a note. Passenger history is never changed.
 */
final class ResolveReviewItem
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportReviewItem $item, string $note, Request $request): TransportReviewItem
    {
        return DB::transaction(function () use ($actor, $item, $note, $request): TransportReviewItem {
            /** @var TransportReviewItem $locked */
            $locked = TransportReviewItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $run = TransportVehicleRun::query()->findOrFail($locked->vehicle_run_id);
            SeasonGuard::assertWritable($run->season()->firstOrFail());

            if ($locked->status === ReviewStatus::Resolved) {
                throw TransportationDomainException::reviewAlreadyResolved();
            }

            $now = Carbon::now();
            $locked->update([
                'status' => ReviewStatus::Resolved,
                'resolved_at' => $now,
                'resolved_by' => $actor->id,
                'resolution_note' => $note,
            ]);

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $run->transport_movement_id,
                type: TimelineEventType::ReviewResolved,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $run->id,
                passengerId: $locked->run_passenger_id,
                reason: $note,
                context: ['review_item_id' => $locked->id, 'review_type' => $locked->review_type->value],
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_REVIEW_RESOLVED, $actor->id, 'transport_review_item', $locked->id, null, $locked->review_type->value, [
                'from_status' => ReviewStatus::Open->value,
                'to_status' => ReviewStatus::Resolved->value,
                'reason' => $note,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'severity' => $locked->severity->value,
                    'season_pilgrim_id' => $locked->season_pilgrim_id,
                    'run_passenger_id' => $locked->run_passenger_id,
                ],
            ], $request);

            return $locked->refresh();
        });
    }
}
