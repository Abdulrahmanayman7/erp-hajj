<?php

namespace App\Modules\Transportation\Actions\Vehicles;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete only before the vehicle joins any season fleet.
 */
final class DeleteTransportVehicle
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportVehicle $vehicle, Request $request): void
    {
        DB::transaction(function () use ($actor, $vehicle, $request): void {
            /** @var TransportVehicle $locked */
            $locked = TransportVehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($locked->seasonVehicles()->exists()) {
                throw TransportationDomainException::vehicleInUse();
            }

            $locked->delete();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_VEHICLE_DELETED, $actor->id, 'transport_vehicle', $locked->id, $locked->vehicle_number, $locked->plate_number, [], $request);
        });
    }
}
