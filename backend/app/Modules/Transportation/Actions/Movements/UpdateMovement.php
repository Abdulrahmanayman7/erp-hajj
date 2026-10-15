<?php

namespace App\Modules\Transportation\Actions\Movements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\CrewResolver;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Draft / Planned only. A new planned window re-checks resource overlap for
 * every Run (I1). Lock order: movement → runs → season vehicles →
 * employees → external drivers.
 */
final class UpdateMovement
{
    public function __construct(
        private readonly ResourceOverlapGuard $overlap,
        private readonly CrewResolver $crew,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportMovement $movement, array $data, Request $request): TransportMovement
    {
        return TransportTransaction::run(function () use ($actor, $movement, $data, $request): TransportMovement {
            /** @var TransportMovement $locked */
            $locked = TransportMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if (! $locked->status->isEditable()) {
                throw TransportationDomainException::movementNotEditable();
            }

            $changes = Arr::only($data, ['origin_point_id', 'destination_point_id', 'planned_departure_at', 'planned_arrival_at', 'notes']);
            $before = TransportAudit::values($locked->only(array_keys($changes)));

            $originId = (int) ($changes['origin_point_id'] ?? $locked->origin_point_id);
            $destinationId = (int) ($changes['destination_point_id'] ?? $locked->destination_point_id);
            if (array_key_exists('origin_point_id', $changes) || array_key_exists('destination_point_id', $changes)) {
                MovementPoints::assertValid($originId, $destinationId);
            }

            $departure = Carbon::parse((string) ($changes['planned_departure_at'] ?? $locked->planned_departure_at));
            $arrival = Carbon::parse((string) ($changes['planned_arrival_at'] ?? $locked->planned_arrival_at));
            MovementPoints::assertWindow($departure, $arrival);

            $windowChanged = ! $departure->equalTo($locked->planned_departure_at) || ! $arrival->equalTo($locked->planned_arrival_at);

            $locked->update([
                ...$changes,
                'planned_departure_at' => $departure,
                'planned_arrival_at' => $arrival,
            ]);

            if ($windowChanged) {
                $this->recheckOverlaps($locked);
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_UPDATED, $actor->id, 'transport_movement', $locked->id, $locked->movement_number, null, [
                'before_values' => $before,
                'after_values' => TransportAudit::values($locked->only(array_keys($changes))),
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return MovementLoader::load($locked);
        });
    }

    private function recheckOverlaps(TransportMovement $movement): void
    {
        $runIds = TransportVehicleRun::query()
            ->where('transport_movement_id', $movement->id)
            ->whereIn('status', RunStatus::occupyingValues())
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($runIds === []) {
            return;
        }

        $vehicles = TransportRunVehicleAssignment::query()->whereIn('vehicle_run_id', $runIds)->whereNull('ended_at')->get();
        $drivers = TransportRunDriverAssignment::query()->whereIn('vehicle_run_id', $runIds)->whereNull('ended_at')->get();

        TransportSeasonVehicle::query()->whereKey($vehicles->pluck('transport_season_vehicle_id')->all())->orderBy('id')->lockForUpdate()->get();
        $employeeIds = $drivers->pluck('driver_employee_id')->filter()->unique()->sort()->values();
        $externalIds = $drivers->pluck('external_driver_id')->filter()->unique()->sort()->values();
        $this->crew->lockEmployees($employeeIds->all());
        $this->crew->lockExternalDrivers($externalIds->all());

        foreach ($vehicles as $assignment) {
            $this->overlap->assertVehicleFree($assignment->transport_season_vehicle_id, $movement, $assignment->vehicle_run_id);
        }

        foreach ($drivers as $assignment) {
            if ($assignment->driver_employee_id !== null) {
                $this->overlap->assertEmployeeDriverFree($assignment->driver_employee_id, $movement, $assignment->vehicle_run_id);
            } else {
                $this->overlap->assertExternalDriverFree((int) $assignment->external_driver_id, $movement, $assignment->vehicle_run_id);
            }
        }
    }
}
