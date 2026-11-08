<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;

/**
 * transport_season_vehicles: operational_capacity, service_level, is_active, notes.
 *
 * The shared vehicle master is resolved by `plate_number` and is NEVER created
 * (a missing master is a preview conflict). Default driver / supervisor /
 * external-driver references are NEVER copied.
 */
final class TransportSeasonVehiclesSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function entityKind(): string
    {
        return SafeCopyAllowlist::TRANSPORT_SEASON_VEHICLES;
    }

    protected function sourceCandidates(Season $source): array
    {
        return $this->rowsFor($source, 'source_id');
    }

    protected function targetRows(Season $target): array
    {
        return $this->rowsFor($target, 'id');
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        if ($this->targetVehicleId($key) !== null) {
            return null;
        }

        return ['dependency' => 'transport_vehicles', 'plate_number' => $key];
    }

    protected function insertTarget(Season $target, array $fields, User $actor): ?int
    {
        $vehicleId = $this->targetVehicleId((string) $fields['plate_number']);
        if ($vehicleId === null) {
            return null;
        }

        $seasonVehicle = new TransportSeasonVehicle([
            'season_id' => $target->id,
            'transport_vehicle_id' => $vehicleId,
            'operational_capacity' => $fields['operational_capacity'],
            'service_level' => $fields['service_level'],
            'is_active' => $fields['is_active'],
            'notes' => $fields['notes'],
            'created_by' => $actor->id,
        ]);
        $seasonVehicle->save();

        return $seasonVehicle->id;
    }

    protected function updateTarget(int $targetId, array $fields): void
    {
        TransportSeasonVehicle::query()->findOrFail($targetId)->update([
            'operational_capacity' => $fields['operational_capacity'],
            'service_level' => $fields['service_level'],
            'is_active' => $fields['is_active'],
            'notes' => $fields['notes'],
        ]);
    }

    private function targetVehicleId(string $plateNumber): ?int
    {
        $id = TransportVehicle::query()->where('plate_number', $plateNumber)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rowsFor(Season $season, string $idKey): array
    {
        $rows = [];
        $seasonVehicles = TransportSeasonVehicle::query()
            ->where('season_id', $season->id)
            ->with('vehicle')
            ->get()
            ->filter(static fn (TransportSeasonVehicle $v): bool => $v->vehicle !== null)
            ->sortBy(static fn (TransportSeasonVehicle $v): string => $v->vehicle->plate_number);

        foreach ($seasonVehicles as $seasonVehicle) {
            $rows[$seasonVehicle->vehicle->plate_number] = [
                $idKey => $seasonVehicle->id,
                'fields' => $this->allowlisted([
                    'plate_number' => $seasonVehicle->vehicle->plate_number,
                    'operational_capacity' => (int) $seasonVehicle->operational_capacity,
                    'service_level' => $seasonVehicle->service_level->value,
                    'is_active' => (bool) $seasonVehicle->is_active,
                    'notes' => $seasonVehicle->notes,
                ]),
            ];
        }

        return $rows;
    }
}
