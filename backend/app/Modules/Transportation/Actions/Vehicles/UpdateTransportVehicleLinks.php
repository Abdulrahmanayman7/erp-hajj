<?php

namespace App\Modules\Transportation\Actions\Vehicles;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T3 / I12: optional read-only Asset / Contract references. The
 * referenced rows are resolved tenant-scoped and never modified.
 */
final class UpdateTransportVehicleLinks
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportVehicle $vehicle, ?int $assetId, ?int $contractId, Request $request): TransportVehicle
    {
        return DB::transaction(function () use ($actor, $vehicle, $assetId, $contractId, $request): TransportVehicle {
            /** @var TransportVehicle $locked */
            $locked = TransportVehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($assetId !== null) {
                if (! Asset::query()->whereKey($assetId)->exists()) {
                    throw TransportationDomainException::linkInvalid('asset_id');
                }

                if (TransportVehicle::query()->where('asset_id', $assetId)->whereKeyNot($locked->id)->exists()) {
                    throw TransportationDomainException::vehicleAssetTaken();
                }
            }

            if ($contractId !== null && ! Contract::query()->whereKey($contractId)->exists()) {
                throw TransportationDomainException::linkInvalid('contract_id');
            }

            $before = ['asset_id' => $locked->asset_id, 'contract_id' => $locked->contract_id];
            $after = ['asset_id' => $assetId, 'contract_id' => $contractId];

            if ($before === $after) {
                return $locked->load(['asset', 'contract']);
            }

            try {
                $locked->update($after);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::vehicleAssetTaken();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_VEHICLE_LINKS_UPDATED, $actor->id, 'transport_vehicle', $locked->id, $locked->vehicle_number, $locked->plate_number, [
                'before_values' => $before,
                'after_values' => $after,
            ], $request);

            return $locked->refresh()->load(['asset', 'contract']);
        });
    }
}
