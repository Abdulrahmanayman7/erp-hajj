<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Models\TransportRunSupervisorAssignment;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\CrewResolver;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T4 / T7: Transport Supervisor (an Employee). Overlap with other
 * Runs is allowed. `null` ends the current supervisor without a successor.
 */
final class AssignRunSupervisor
{
    public function __construct(
        private readonly CrewResolver $crew,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportVehicleRun $run, ?int $employeeId, ?string $reason, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $employeeId, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());
            $reason = RunSubstitution::assertAllowed($locked, $reason);

            /** @var TransportRunSupervisorAssignment|null $current */
            $current = $locked->currentSupervisorAssignment()->lockForUpdate()->first();
            if ($current?->supervisor_employee_id === $employeeId) {
                return RunLoader::load($locked);
            }

            if ($employeeId !== null) {
                $this->crew->activeEmployee($employeeId, 'supervisor_employee_id');
            }

            $now = Carbon::now();
            $current?->update(['ended_at' => $now, 'ended_by' => $actor->id]);

            $assignment = $employeeId === null ? null : TransportRunSupervisorAssignment::query()->create([
                'season_id' => $locked->season_id,
                'vehicle_run_id' => $locked->id,
                'supervisor_employee_id' => $employeeId,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $before = ['supervisor_employee_id' => $current?->supervisor_employee_id];
            $after = ['supervisor_employee_id' => $employeeId];

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $movement->id,
                type: TimelineEventType::SupervisorAssigned,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $locked->id,
                reason: $reason,
                context: ['previous' => $before, 'current' => $after, 'assignment_id' => $assignment?->id],
            );

            $this->audit->record(
                $employeeId === null ? AuthorizationSecurityEvent::TRANSPORT_RUN_SUPERVISOR_ENDED : AuthorizationSecurityEvent::TRANSPORT_RUN_SUPERVISOR_ASSIGNED,
                $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null,
                [
                    'before_values' => $before,
                    'after_values' => $after,
                    'reason' => $reason,
                    'metadata' => ['season_id' => $locked->season_id, 'run_status' => $locked->status->value],
                ],
                $request,
            );

            return RunLoader::load($locked);
        }, 5);
    }
}
