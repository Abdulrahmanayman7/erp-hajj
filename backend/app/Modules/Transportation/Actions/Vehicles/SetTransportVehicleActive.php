<?php

namespace App\Modules\Transportation\Actions\Vehicles;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Deactivation is never blocked by history; it only stops new season-fleet
 * registrations.
 */
final class SetTransportVehicleActive
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportVehicle $vehicle, bool $active, Request $request): TransportVehicle
    {
        return DB::transaction(function () use ($actor, $vehicle, $active, $request): TransportVehicle {
            /** @var TransportVehicle $locked */
            $locked = TransportVehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active !== $active) {
                $locked->update(['is_active' => $active]);

                $this->audit->record(
                    $active ? AuthorizationSecurityEvent::TRANSPORT_VEHICLE_ACTIVATED : AuthorizationSecurityEvent::TRANSPORT_VEHICLE_DEACTIVATED,
                    $actor->id, 'transport_vehicle', $locked->id, $locked->vehicle_number, $locked->plate_number,
                    ['before_values' => ['is_active' => ! $active], 'after_values' => ['is_active' => $active]],
                    $request,
                );
            }

            return $locked;
        });
    }
}
