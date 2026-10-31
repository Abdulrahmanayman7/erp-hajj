<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunSupervisorAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\CrewResolver;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\SeasonVehicleResolver;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T1 / T4 / T7 / I5: a Run is created with a SeasonVehicle; the
 * SeasonVehicle's default driver / supervisor become the Run's first
 * current assignment-history rows (validated like explicit assignments:
 * active, same tenant, no driver overlap). run_created summarizes them;
 * it does not replace history.
 */
final class CreateRun
{
    public function __construct(
        private readonly CrewResolver $crew,
        private readonly ResourceOverlapGuard $overlap,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportMovement $movement, array $data, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $movement, $data, $request): TransportVehicleRun {
            /** @var TransportMovement $locked */
            $locked = TransportMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->status->isTerminal()) {
                throw TransportationDomainException::movementNotEditable();
            }

            $seasonVehicle = SeasonVehicleResolver::activeInSeason($locked->season_id, (int) $data['transport_season_vehicle_id']);

            $override = isset($data['capacity_override']) ? (int) $data['capacity_override'] : null;
            if ($override !== null && $override > $seasonVehicle->operational_capacity) {
                throw TransportationDomainException::runCapacityOverrideInvalid();
            }

            $this->overlap->assertVehicleFree($seasonVehicle->id, $locked);

            $employeeIds = array_values(array_unique(array_filter([
                $seasonVehicle->default_driver_employee_id,
                $seasonVehicle->default_supervisor_employee_id,
            ])));
            sort($employeeIds);
            foreach ($employeeIds as $employeeId) {
                $this->crew->activeEmployee(
                    $employeeId,
                    $employeeId === $seasonVehicle->default_driver_employee_id ? 'default_driver_employee_id' : 'default_supervisor_employee_id',
                );
            }
            if ($seasonVehicle->default_external_driver_id !== null) {
                $this->crew->activeExternalDriver($seasonVehicle->default_external_driver_id);
            }

            if ($seasonVehicle->default_driver_employee_id !== null) {
                $this->overlap->assertEmployeeDriverFree($seasonVehicle->default_driver_employee_id, $locked);
            }
            if ($seasonVehicle->default_external_driver_id !== null) {
                $this->overlap->assertExternalDriverFree($seasonVehicle->default_external_driver_id, $locked);
            }

            $runNumber = (int) TransportVehicleRun::query()->where('transport_movement_id', $locked->id)->max('run_number') + 1;
            $now = Carbon::now();

            $run = TransportVehicleRun::query()->create([
                'season_id' => $locked->season_id,
                'transport_movement_id' => $locked->id,
                'run_number' => $runNumber,
                'status' => RunStatus::Planned,
                'capacity_override' => $override,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $base = ['season_id' => $locked->season_id, 'vehicle_run_id' => $run->id, 'assigned_at' => $now, 'assigned_by' => $actor->id];

            TransportRunVehicleAssignment::query()->create([...$base, 'transport_season_vehicle_id' => $seasonVehicle->id]);

            $hasDriver = $seasonVehicle->default_driver_employee_id !== null || $seasonVehicle->default_external_driver_id !== null;
            if ($hasDriver) {
                TransportRunDriverAssignment::query()->create([
                    ...$base,
                    'driver_employee_id' => $seasonVehicle->default_driver_employee_id,
                    'external_driver_id' => $seasonVehicle->default_external_driver_id,
                ]);
            }

            if ($seasonVehicle->default_supervisor_employee_id !== null) {
                TransportRunSupervisorAssignment::query()->create([...$base, 'supervisor_employee_id' => $seasonVehicle->default_supervisor_employee_id]);
            }

            $context = [
                'run_number' => $runNumber,
                'transport_season_vehicle_id' => $seasonVehicle->id,
                'driver_employee_id' => $seasonVehicle->default_driver_employee_id,
                'external_driver_id' => $seasonVehicle->default_external_driver_id,
                'supervisor_employee_id' => $seasonVehicle->default_supervisor_employee_id,
                'capacity_override' => $override,
            ];

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $locked->id,
                type: TimelineEventType::RunCreated,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $run->id,
                context: array_filter($context, fn ($v) => $v !== null),
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_CREATED, $actor->id, 'transport_vehicle_run', $run->id, $locked->movement_number.'/'.$runNumber, null, [
                'after_values' => $context,
                'metadata' => ['season_id' => $locked->season_id, 'transport_movement_id' => $locked->id],
            ], $request);

            return RunLoader::load($run);
        }, 5);
    }
}
