<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Capacity cannot drop below the occupied count or capacity override of
 * any non-terminal Run currently using the vehicle. Only the SeasonVehicle
 * row is locked; manifest writers lock it after their Run, so they always
 * read the committed capacity.
 */
final class UpdateSeasonVehicle
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportSeasonVehicle $seasonVehicle, array $data, Request $request): TransportSeasonVehicle
    {
        return DB::transaction(function () use ($actor, $seasonVehicle, $data, $request): TransportSeasonVehicle {
            /** @var TransportSeasonVehicle $locked */
            $locked = TransportSeasonVehicle::query()->whereKey($seasonVehicle->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $changes = Arr::only($data, ['operational_capacity', 'service_level', 'notes']);

            if (array_key_exists('operational_capacity', $changes)) {
                $capacity = (int) $changes['operational_capacity'];
                $this->assertCapacityFits($locked, $capacity);
                $changes['operational_capacity'] = $capacity;
            }

            $before = TransportAudit::values($locked->only(array_keys($changes)));
            $locked->update($changes);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_UPDATED, $actor->id, 'transport_season_vehicle', $locked->id, null, null, [
                'before_values' => $before,
                'after_values' => $changes,
                'metadata' => ['season_id' => $locked->season_id, 'transport_vehicle_id' => $locked->transport_vehicle_id],
            ], $request);

            return SeasonVehicleLoader::load($locked);
        });
    }

    private function assertCapacityFits(TransportSeasonVehicle $seasonVehicle, int $capacity): void
    {
        $runs = TransportVehicleRun::query()
            ->whereIn('status', RunStatus::occupyingValues())
            ->whereHas('currentVehicleAssignment', fn ($q) => $q->where('transport_season_vehicle_id', $seasonVehicle->id))
            ->get(['id', 'capacity_override']);

        foreach ($runs as $run) {
            $occupied = TransportRunPassenger::query()
                ->where('vehicle_run_id', $run->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->count();

            if ($occupied > $capacity || ($run->capacity_override !== null && $run->capacity_override > $capacity)) {
                throw TransportationDomainException::capacityBelowAssigned();
            }
        }
    }
}
