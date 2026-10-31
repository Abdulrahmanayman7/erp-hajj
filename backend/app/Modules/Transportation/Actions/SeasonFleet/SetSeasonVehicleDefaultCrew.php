<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Support\CrewResolver;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T4: planning defaults only. Existing Runs keep their actual
 * crew.
 */
final class SetSeasonVehicleDefaultCrew
{
    public function __construct(
        private readonly CrewResolver $crew,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(
        User $actor,
        TransportSeasonVehicle $seasonVehicle,
        ?int $driverEmployeeId,
        ?int $externalDriverId,
        ?int $supervisorEmployeeId,
        Request $request,
    ): TransportSeasonVehicle {
        if ($driverEmployeeId !== null && $externalDriverId !== null) {
            throw TransportationDomainException::driverInvalid();
        }

        return DB::transaction(function () use ($actor, $seasonVehicle, $driverEmployeeId, $externalDriverId, $supervisorEmployeeId, $request): TransportSeasonVehicle {
            /** @var TransportSeasonVehicle $locked */
            $locked = TransportSeasonVehicle::query()->whereKey($seasonVehicle->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $employeeIds = array_values(array_unique(array_filter([$driverEmployeeId, $supervisorEmployeeId])));
            sort($employeeIds);

            foreach ($employeeIds as $employeeId) {
                $this->crew->activeEmployee(
                    $employeeId,
                    $employeeId === $driverEmployeeId ? 'default_driver_employee_id' : 'default_supervisor_employee_id',
                );
            }

            if ($externalDriverId !== null) {
                $this->crew->activeExternalDriver($externalDriverId);
            }

            $after = [
                'default_driver_employee_id' => $driverEmployeeId,
                'default_external_driver_id' => $externalDriverId,
                'default_supervisor_employee_id' => $supervisorEmployeeId,
            ];
            $before = $locked->only(array_keys($after));

            $locked->update($after);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_DEFAULT_CREW_UPDATED, $actor->id, 'transport_season_vehicle', $locked->id, null, null, [
                'before_values' => $before,
                'after_values' => $after,
                'metadata' => ['season_id' => $locked->season_id, 'transport_vehicle_id' => $locked->transport_vehicle_id],
            ], $request);

            return SeasonVehicleLoader::load($locked);
        });
    }
}
