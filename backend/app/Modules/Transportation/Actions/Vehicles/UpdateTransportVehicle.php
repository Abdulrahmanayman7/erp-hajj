<?php

namespace App\Modules\Transportation\Actions\Vehicles;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTransportVehicle
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportVehicle $vehicle, array $data, Request $request): TransportVehicle
    {
        return DB::transaction(function () use ($actor, $vehicle, $data, $request): TransportVehicle {
            /** @var TransportVehicle $locked */
            $locked = TransportVehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            $changes = [];
            if (array_key_exists('plate_number', $data)) {
                $changes['plate_number'] = CreateTransportVehicle::normalizePlate((string) $data['plate_number']);
                if (TransportVehicle::query()->where('plate_number', $changes['plate_number'])->whereKeyNot($locked->id)->exists()) {
                    throw TransportationDomainException::vehiclePlateTaken();
                }
            }
            if (array_key_exists('vehicle_class', $data)) {
                $changes['vehicle_class'] = $data['vehicle_class'];
            }
            if (array_key_exists('notes', $data)) {
                $changes['notes'] = $data['notes'];
            }

            $before = TransportAudit::values($locked->only(array_keys($changes)));

            try {
                $locked->update($changes);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::vehiclePlateTaken();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_VEHICLE_UPDATED, $actor->id, 'transport_vehicle', $locked->id, $locked->vehicle_number, $locked->plate_number, [
                'before_values' => $before,
                'after_values' => $changes,
            ], $request);

            return $locked->refresh();
        });
    }
}
