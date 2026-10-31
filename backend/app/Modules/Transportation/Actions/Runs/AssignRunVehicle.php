<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\SeasonVehicleResolver;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T7: vehicle substitution appends history; after execution began
 * a reason is required. The new vehicle must fit the occupied count and
 * the Run's capacity override, and must not overlap another Run.
 */
final class AssignRunVehicle
{
    public function __construct(
        private readonly ResourceOverlapGuard $overlap,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportVehicleRun $run, int $seasonVehicleId, ?string $reason, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $seasonVehicleId, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());
            $reason = RunSubstitution::assertAllowed($locked, $reason);

            $seasonVehicle = SeasonVehicleResolver::activeInSeason($locked->season_id, $seasonVehicleId);

            /** @var TransportRunVehicleAssignment|null $current */
            $current = $locked->currentVehicleAssignment()->lockForUpdate()->first();
            if ($current !== null && $current->transport_season_vehicle_id === $seasonVehicle->id) {
                return RunLoader::load($locked);
            }

            $this->overlap->assertVehicleFree($seasonVehicle->id, $movement, $locked->id);

            if ($locked->capacity_override !== null && $locked->capacity_override > $seasonVehicle->operational_capacity) {
                throw TransportationDomainException::runCapacityOverrideInvalid();
            }
            if ($locked->occupiedCount() > $seasonVehicle->operational_capacity) {
                throw TransportationDomainException::capacityBelowAssigned();
            }

            $now = Carbon::now();
            $current?->update(['ended_at' => $now, 'ended_by' => $actor->id]);

            $assignment = TransportRunVehicleAssignment::query()->create([
                'season_id' => $locked->season_id,
                'vehicle_run_id' => $locked->id,
                'transport_season_vehicle_id' => $seasonVehicle->id,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $context = [
                'previous_season_vehicle_id' => $current?->transport_season_vehicle_id,
                'transport_season_vehicle_id' => $seasonVehicle->id,
                'assignment_id' => $assignment->id,
            ];

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $movement->id,
                type: TimelineEventType::VehicleAssigned,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $locked->id,
                reason: $reason,
                context: $context,
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_VEHICLE_ASSIGNED, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'before_values' => ['transport_season_vehicle_id' => $current?->transport_season_vehicle_id],
                'after_values' => ['transport_season_vehicle_id' => $seasonVehicle->id],
                'reason' => $reason,
                'metadata' => ['season_id' => $locked->season_id, 'run_status' => $locked->status->value],
            ], $request);

            return RunLoader::load($locked);
        }, 5);
    }
}
