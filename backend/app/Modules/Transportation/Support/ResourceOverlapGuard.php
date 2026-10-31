<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * ADR-0018 T7 / I1: a SeasonVehicle, an internal driver, or an
 * ExternalDriver may not be current on two Runs in planned / boarding /
 * departed / arrived whose Movement windows intersect. Callers lock the
 * resource row first so concurrent assignments serialize.
 */
final class ResourceOverlapGuard
{
    public function assertVehicleFree(int $seasonVehicleId, TransportMovement $movement, ?int $excludeRunId = null): void
    {
        $query = TransportRunVehicleAssignment::query()
            ->whereNull('ended_at')
            ->where('transport_season_vehicle_id', $seasonVehicleId);

        if ($this->overlaps($query, $movement->planned_departure_at, $movement->planned_arrival_at, $excludeRunId)) {
            throw TransportationDomainException::vehicleOverlap();
        }
    }

    public function assertEmployeeDriverFree(int $employeeId, TransportMovement $movement, ?int $excludeRunId = null): void
    {
        $query = TransportRunDriverAssignment::query()
            ->whereNull('ended_at')
            ->where('driver_employee_id', $employeeId);

        if ($this->overlaps($query, $movement->planned_departure_at, $movement->planned_arrival_at, $excludeRunId)) {
            throw TransportationDomainException::driverOverlap();
        }
    }

    public function assertExternalDriverFree(int $externalDriverId, TransportMovement $movement, ?int $excludeRunId = null): void
    {
        $query = TransportRunDriverAssignment::query()
            ->whereNull('ended_at')
            ->where('external_driver_id', $externalDriverId);

        if ($this->overlaps($query, $movement->planned_departure_at, $movement->planned_arrival_at, $excludeRunId)) {
            throw TransportationDomainException::driverOverlap();
        }
    }

    /**
     * Locking read: under MySQL REPEATABLE READ a plain EXISTS after waiting
     * on another resource can still see a stale snapshot and miss the winner's
     * committed assignment. FOR UPDATE forces the latest committed rows.
     *
     * @param  Builder<TransportRunVehicleAssignment>|Builder<TransportRunDriverAssignment>  $query
     */
    private function overlaps(Builder $query, Carbon $from, Carbon $to, ?int $excludeRunId): bool
    {
        if ($excludeRunId !== null) {
            $query->where('vehicle_run_id', '!=', $excludeRunId);
        }

        return $query
            ->whereHas('run', fn (Builder $run) => $run
                ->whereIn('status', RunStatus::occupyingValues())
                ->whereHas('movement', fn (Builder $movement) => $movement
                    ->where('planned_departure_at', '<', $to)
                    ->where('planned_arrival_at', '>', $from)))
            ->orderBy('id')
            ->lockForUpdate()
            ->exists();
    }
}
