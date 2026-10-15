<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\CrewResolver;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ADR-0018 T4 / T7: the driver is an active internal Employee or an active
 * ExternalDriver (exactly one). Substitution appends history and is
 * rejected when the driver is current on an overlapping Run.
 */
final class AssignRunDriver
{
    public function __construct(
        private readonly CrewResolver $crew,
        private readonly ResourceOverlapGuard $overlap,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportVehicleRun $run, ?int $employeeId, ?int $externalDriverId, ?string $reason, Request $request): TransportVehicleRun
    {
        if (($employeeId === null) === ($externalDriverId === null)) {
            throw TransportationDomainException::driverInvalid();
        }

        return TransportTransaction::run(function () use ($actor, $run, $employeeId, $externalDriverId, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());
            $reason = RunSubstitution::assertAllowed($locked, $reason);

            // Driver row before the history read: the history read may take a
            // gap lock, which must not be held while waiting for the driver.
            if ($employeeId !== null) {
                $this->crew->activeEmployee($employeeId, 'driver_employee_id');
            } else {
                $this->crew->activeExternalDriver((int) $externalDriverId);
            }

            /** @var TransportRunDriverAssignment|null $current */
            $current = $locked->currentDriverAssignment()->lockForUpdate()->first();
            if ($current !== null && $current->driver_employee_id === $employeeId && $current->external_driver_id === $externalDriverId) {
                return RunLoader::load($locked);
            }

            if ($employeeId !== null) {
                $this->overlap->assertEmployeeDriverFree($employeeId, $movement, $locked->id);
            } else {
                $this->overlap->assertExternalDriverFree((int) $externalDriverId, $movement, $locked->id);
            }

            $now = Carbon::now();
            $current?->update(['ended_at' => $now, 'ended_by' => $actor->id]);

            $assignment = TransportRunDriverAssignment::query()->create([
                'season_id' => $locked->season_id,
                'vehicle_run_id' => $locked->id,
                'driver_employee_id' => $employeeId,
                'external_driver_id' => $externalDriverId,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $before = ['driver_employee_id' => $current?->driver_employee_id, 'external_driver_id' => $current?->external_driver_id];
            $after = ['driver_employee_id' => $employeeId, 'external_driver_id' => $externalDriverId];

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $movement->id,
                type: TimelineEventType::DriverAssigned,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $locked->id,
                reason: $reason,
                context: ['previous' => $before, 'current' => $after, 'assignment_id' => $assignment->id],
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_DRIVER_ASSIGNED, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'before_values' => $before,
                'after_values' => $after,
                'reason' => $reason,
                'metadata' => ['season_id' => $locked->season_id, 'run_status' => $locked->status->value],
            ], $request);

            return RunLoader::load($locked);
        });
    }
}
