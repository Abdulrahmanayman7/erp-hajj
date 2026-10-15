<?php

namespace App\Modules\Transportation\Support;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Collection;

/**
 * Sets the live `entitlement_status` (T9 / I10) on passenger rows for the
 * manifest: override, else current Program vs. the Run's current vehicle.
 */
final class PassengerEntitlementStatuses
{
    public function __construct(private readonly EntitlementEvaluator $entitlements) {}

    /**
     * @param  Collection<int, TransportRunPassenger>  $passengers
     */
    public function annotate(Collection $passengers): void
    {
        if ($passengers->isEmpty()) {
            return;
        }

        $programs = SeasonPilgrim::query()
            ->whereKey($passengers->pluck('season_pilgrim_id')->unique()->values()->all())
            ->pluck('season_program_id', 'id');

        $vehicleByRun = TransportRunVehicleAssignment::query()
            ->whereIn('vehicle_run_id', $passengers->pluck('vehicle_run_id')->unique()->values()->all())
            ->whereNull('ended_at')
            ->pluck('transport_season_vehicle_id', 'vehicle_run_id');

        $seasonVehicles = TransportSeasonVehicle::query()
            ->with('vehicle')
            ->whereKey($vehicleByRun->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($passengers as $passenger) {
            $programId = $programs->get($passenger->season_pilgrim_id);
            $status = $this->entitlements->liveStatus(
                $passenger,
                $programId !== null ? (int) $programId : null,
                $seasonVehicles->get($vehicleByRun->get($passenger->vehicle_run_id)),
            );

            $passenger->setAttribute('live_entitlement_status', $status->value);
        }
    }
}
