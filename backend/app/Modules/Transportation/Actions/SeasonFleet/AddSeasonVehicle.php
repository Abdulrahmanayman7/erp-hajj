<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T3: registers an active tenant Vehicle in the season fleet,
 * once per season.
 */
final class AddSeasonVehicle
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): TransportSeasonVehicle
    {
        return DB::transaction(function () use ($actor, $season, $data, $request): TransportSeasonVehicle {
            SeasonGuard::assertWritable($season);

            /** @var TransportVehicle|null $vehicle */
            $vehicle = TransportVehicle::query()->whereKey((int) $data['transport_vehicle_id'])->lockForUpdate()->first();
            if ($vehicle === null) {
                throw TransportationDomainException::linkInvalid('transport_vehicle_id');
            }
            if (! $vehicle->is_active) {
                throw TransportationDomainException::vehicleInactive();
            }

            if (TransportSeasonVehicle::query()->where('season_id', $season->id)->where('transport_vehicle_id', $vehicle->id)->exists()) {
                throw TransportationDomainException::seasonVehicleDuplicate();
            }

            try {
                $seasonVehicle = TransportSeasonVehicle::query()->create([
                    'season_id' => $season->id,
                    'transport_vehicle_id' => $vehicle->id,
                    'operational_capacity' => (int) $data['operational_capacity'],
                    'service_level' => $data['service_level'],
                    'notes' => $data['notes'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::seasonVehicleDuplicate();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_ADDED, $actor->id, 'transport_season_vehicle', $seasonVehicle->id, $vehicle->vehicle_number, $vehicle->plate_number, [
                'after_values' => [
                    'transport_vehicle_id' => $vehicle->id,
                    'operational_capacity' => $seasonVehicle->operational_capacity,
                    'service_level' => $seasonVehicle->service_level->value,
                ],
                'metadata' => ['season_id' => $season->id],
            ], $request);

            return SeasonVehicleLoader::load($seasonVehicle);
        });
    }
}
