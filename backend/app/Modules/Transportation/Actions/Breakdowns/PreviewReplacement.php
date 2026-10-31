<?php

namespace App\Modules\Transportation\Actions\Breakdowns;

use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\SeasonVehicleResolver;

/**
 * Read-only capacity / eligibility preview for a replacement candidate.
 *
 * @return array<string, mixed>
 */
final class PreviewReplacement
{
    public function __construct(private readonly ResourceOverlapGuard $overlap) {}

    public function execute(TransportVehicleBreakdown $breakdown, int $replacementSeasonVehicleId): array
    {
        if ($breakdown->status !== BreakdownStatus::Open) {
            throw TransportationDomainException::breakdownNotOpen();
        }

        /** @var TransportVehicleRun $run */
        $run = TransportVehicleRun::query()->whereKey($breakdown->vehicle_run_id)->firstOrFail();
        $run->load('movement');

        $current = $run->currentVehicleAssignment;
        if ($current === null || (int) $current->transport_season_vehicle_id !== (int) $breakdown->transport_season_vehicle_id) {
            throw TransportationDomainException::breakdownVehicleMismatch();
        }

        if ($replacementSeasonVehicleId === (int) $breakdown->transport_season_vehicle_id) {
            throw TransportationDomainException::replacementSameVehicle();
        }

        $replacement = SeasonVehicleResolver::activeInSeason($breakdown->season_id, $replacementSeasonVehicleId);

        $overlapOk = true;
        try {
            $this->overlap->assertVehicleFree($replacement->id, $run->movement, $run->id);
        } catch (TransportationDomainException) {
            $overlapOk = false;
        }

        $occupied = $run->occupiedCount();
        $boarded = TransportRunPassenger::query()
            ->where('vehicle_run_id', $run->id)
            ->whereIn('status', [PassengerStatus::Boarded->value, PassengerStatus::Arrived->value])
            ->count();
        $assigned = TransportRunPassenger::query()
            ->where('vehicle_run_id', $run->id)
            ->where('status', PassengerStatus::Assigned->value)
            ->orderBy('id')
            ->get(['id', 'season_pilgrim_id', 'status']);

        $capacity = (int) $replacement->operational_capacity;
        if ($run->capacity_override !== null) {
            $capacity = min($capacity, (int) $run->capacity_override);
        }

        $gap = max(0, $occupied - $capacity);

        $eligibleRuns = TransportVehicleRun::query()
            ->where('season_id', $breakdown->season_id)
            ->whereKeyNot($run->id)
            ->whereIn('status', [RunStatus::Planned->value, RunStatus::Boarding->value])
            ->with(['currentVehicleAssignment.seasonVehicle.vehicle', 'movement'])
            ->orderBy('id')
            ->get()
            ->map(function (TransportVehicleRun $candidate) use ($capacity): ?array {
                $sv = $candidate->currentVehicleAssignment?->seasonVehicle;
                if ($sv === null || ! $sv->is_active) {
                    return null;
                }
                $eff = $candidate->capacity_override ?? $sv->operational_capacity;
                $free = max(0, (int) $eff - $candidate->occupiedCount());
                if ($free < 1) {
                    return null;
                }

                return [
                    'id' => $candidate->id,
                    'run_number' => $candidate->run_number,
                    'status' => $candidate->status->value,
                    'movement_number' => $candidate->movement?->movement_number,
                    'available_seats' => $free,
                    'plate_number' => $sv->vehicle?->plate_number,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'breakdown_id' => $breakdown->id,
            'vehicle_run_id' => $run->id,
            'occupied_count' => $occupied,
            'boarded_or_arrived_count' => $boarded,
            'assigned_movable' => $assigned->map(fn (TransportRunPassenger $p): array => [
                'id' => $p->id,
                'season_pilgrim_id' => $p->season_pilgrim_id,
                'status' => $p->status->value,
            ])->values()->all(),
            'replacement' => [
                'transport_season_vehicle_id' => $replacement->id,
                'operational_capacity' => $replacement->operational_capacity,
                'effective_capacity' => $capacity,
                'plate_number' => $replacement->vehicle?->plate_number,
                'overlap_free' => $overlapOk,
            ],
            'capacity_gap' => $gap,
            'plan_required' => $gap > 0,
            'boarded_block' => $boarded > $capacity,
            'eligible_target_runs' => $eligibleRuns,
        ];
    }
}
