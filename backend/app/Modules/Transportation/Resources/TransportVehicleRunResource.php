<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunSupervisorAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportVehicleRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportVehicleRun
 */
class TransportVehicleRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportVehicleRun $run */
        $run = $this->resource;

        $currentVehicle = $run->relationLoaded('currentVehicleAssignment') ? $run->currentVehicleAssignment : null;
        $seasonVehicle = $currentVehicle?->relationLoaded('seasonVehicle') ? $currentVehicle->seasonVehicle : null;

        return [
            'id' => $run->id,
            'season_id' => $run->season_id,
            'transport_movement_id' => $run->transport_movement_id,
            'movement' => $this->whenLoaded('movement', fn () => [
                'id' => $run->movement->id,
                'movement_number' => $run->movement->movement_number,
                'status' => $run->movement->status->value,
                'origin' => $run->movement->relationLoaded('origin') ? ['id' => $run->movement->origin->id, 'code' => $run->movement->origin->code, 'name' => $run->movement->origin->name] : null,
                'destination' => $run->movement->relationLoaded('destination') ? ['id' => $run->movement->destination->id, 'code' => $run->movement->destination->code, 'name' => $run->movement->destination->name] : null,
                'planned_departure_at' => $run->movement->planned_departure_at?->toIso8601String(),
                'planned_arrival_at' => $run->movement->planned_arrival_at?->toIso8601String(),
            ]),
            'run_number' => $run->run_number,
            'status' => $run->status->value,
            'capacity_override' => $run->capacity_override,
            'effective_capacity' => $run->capacity_override ?? $seasonVehicle?->operational_capacity,
            'occupied_count' => $run->occupied_count !== null ? (int) $run->occupied_count : null,
            'current_vehicle' => $this->whenLoaded('currentVehicleAssignment', fn () => self::vehicleAssignment($currentVehicle)),
            'current_driver' => $this->whenLoaded('currentDriverAssignment', fn () => self::driverAssignment($run->currentDriverAssignment)),
            'current_supervisor' => $this->whenLoaded('currentSupervisorAssignment', fn () => self::supervisorAssignment($run->currentSupervisorAssignment)),
            'vehicle_history' => $this->whenLoaded('vehicleAssignments', fn () => $run->vehicleAssignments->map(fn ($a) => self::vehicleAssignment($a))->all()),
            'driver_history' => $this->whenLoaded('driverAssignments', fn () => $run->driverAssignments->map(fn ($a) => self::driverAssignment($a))->all()),
            'supervisor_history' => $this->whenLoaded('supervisorAssignments', fn () => $run->supervisorAssignments->map(fn ($a) => self::supervisorAssignment($a))->all()),
            'boarding_started_at' => $run->boarding_started_at?->toIso8601String(),
            'departed_at' => $run->departed_at?->toIso8601String(),
            'arrived_at' => $run->arrived_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'cancelled_at' => $run->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $run->cancellation_reason,
            'notes' => $run->notes,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function vehicleAssignment(?TransportRunVehicleAssignment $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        $seasonVehicle = $assignment->relationLoaded('seasonVehicle') ? $assignment->seasonVehicle : null;
        $vehicle = $seasonVehicle?->relationLoaded('vehicle') ? $seasonVehicle->vehicle : null;

        return [
            'assignment_id' => $assignment->id,
            'transport_season_vehicle_id' => $assignment->transport_season_vehicle_id,
            'vehicle_number' => $vehicle?->vehicle_number,
            'plate_number' => $vehicle?->plate_number,
            'vehicle_class' => $vehicle?->vehicle_class->value,
            'service_level' => $seasonVehicle?->service_level->value,
            'operational_capacity' => $seasonVehicle?->operational_capacity,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'reason' => $assignment->reason,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function driverAssignment(?TransportRunDriverAssignment $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        return [
            'assignment_id' => $assignment->id,
            'driver_type' => $assignment->driverType(),
            'employee' => CrewSummary::employee($assignment->driver_employee_id !== null ? $assignment->employee : null),
            'external_driver' => CrewSummary::externalDriver($assignment->external_driver_id !== null ? $assignment->externalDriver : null),
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'reason' => $assignment->reason,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function supervisorAssignment(?TransportRunSupervisorAssignment $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        return [
            'assignment_id' => $assignment->id,
            'employee' => CrewSummary::employee($assignment->employee),
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'reason' => $assignment->reason,
        ];
    }
}
