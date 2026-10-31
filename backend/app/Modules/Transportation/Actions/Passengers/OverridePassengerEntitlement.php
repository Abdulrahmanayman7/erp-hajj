<?php

namespace App\Modules\Transportation\Actions\Passengers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Support\EntitlementEvaluator;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T9 / I10: acknowledges a live entitlement mismatch on an
 * existing passenger (permission + reason + audit). The passenger is never
 * moved and the assignment snapshot is kept.
 */
final class OverridePassengerEntitlement
{
    public function __construct(
        private readonly EntitlementEvaluator $entitlements,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportRunPassenger $passenger, string $reason, Request $request): TransportRunPassenger
    {
        return DB::transaction(function () use ($actor, $passenger, $reason, $request): TransportRunPassenger {
            [$movement, $run] = RunLocks::lock($passenger->vehicle_run_id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            /** @var TransportRunPassenger $locked */
            $locked = TransportRunPassenger::query()->whereKey($passenger->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PassengerStatus::Assigned, PassengerStatus::Boarded], true)) {
                throw TransportationDomainException::passengerInvalidTransition([$locked->id]);
            }

            $programId = SeasonPilgrim::query()->whereKey($locked->season_pilgrim_id)->value('season_program_id');
            $seasonVehicleId = $run->currentVehicleAssignment()->value('transport_season_vehicle_id');
            $seasonVehicle = TransportSeasonVehicle::query()->with('vehicle')->find($seasonVehicleId);

            if ($this->entitlements->liveStatus($locked, $programId !== null ? (int) $programId : null, $seasonVehicle) !== EntitlementStatus::Mismatch) {
                throw TransportationDomainException::entitlementOverrideNotNeeded();
            }

            $now = Carbon::now();
            $locked->update([
                'entitlement_override' => true,
                'entitlement_override_reason' => $reason,
                'entitlement_overridden_by' => $actor->id,
                'entitlement_overridden_at' => $now,
            ]);

            $this->timeline->record(
                seasonId: $run->season_id,
                movementId: $movement->id,
                type: TimelineEventType::PassengerEntitlementOverridden,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $run->id,
                passengerId: $locked->id,
                reason: $reason,
                context: ['season_pilgrim_id' => $locked->season_pilgrim_id, 'current_program_id' => $programId],
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_ENTITLEMENT_OVERRIDDEN, $actor->id, 'transport_run_passenger', $locked->id, $movement->movement_number.'/'.$run->run_number, null, [
                'reason' => $reason,
                'before_values' => ['entitlement_override' => false],
                'after_values' => ['entitlement_override' => true],
                'metadata' => ['season_id' => $run->season_id, 'season_pilgrim_id' => $locked->season_pilgrim_id, 'current_program_id' => $programId],
            ], $request);

            return PassengerLoader::load($locked);
        });
    }
}
