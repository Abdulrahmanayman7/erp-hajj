<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete only before any Run assignment; otherwise deactivate.
 */
final class RemoveSeasonVehicle
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportSeasonVehicle $seasonVehicle, Request $request): void
    {
        DB::transaction(function () use ($actor, $seasonVehicle, $request): void {
            /** @var TransportSeasonVehicle $locked */
            $locked = TransportSeasonVehicle::query()->whereKey($seasonVehicle->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->isUsed()) {
                throw TransportationDomainException::seasonVehicleInUse();
            }

            $locked->delete();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_REMOVED, $actor->id, 'transport_season_vehicle', $locked->id, null, null, [
                'metadata' => ['season_id' => $locked->season_id, 'transport_vehicle_id' => $locked->transport_vehicle_id],
            ], $request);
        });
    }
}
