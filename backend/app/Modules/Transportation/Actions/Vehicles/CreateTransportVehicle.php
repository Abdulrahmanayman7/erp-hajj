<?php

namespace App\Modules\Transportation\Actions\Vehicles;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateTransportVehicle
{
    public function __construct(
        private readonly TransportNumberGenerator $numbers,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): TransportVehicle
    {
        return DB::transaction(function () use ($actor, $data, $request): TransportVehicle {
            $plate = self::normalizePlate((string) $data['plate_number']);

            if (TransportVehicle::query()->where('plate_number', $plate)->exists()) {
                throw TransportationDomainException::vehiclePlateTaken();
            }

            try {
                $vehicle = TransportVehicle::query()->create([
                    'vehicle_number' => $this->numbers->nextVehicleNumber(),
                    'plate_number' => $plate,
                    'vehicle_class' => $data['vehicle_class'],
                    'notes' => $data['notes'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::vehiclePlateTaken();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_VEHICLE_CREATED, $actor->id, 'transport_vehicle', $vehicle->id, $vehicle->vehicle_number, $vehicle->plate_number, [
                'after_values' => ['plate_number' => $vehicle->plate_number, 'vehicle_class' => $vehicle->vehicle_class->value],
            ], $request);

            return $vehicle;
        });
    }

    public static function normalizePlate(string $plate): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($plate)));
    }
}
