<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Deactivation stops new Run assignments; existing Runs are unaffected.
 */
final class SetSeasonVehicleActive
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportSeasonVehicle $seasonVehicle, bool $active, Request $request): TransportSeasonVehicle
    {
        return DB::transaction(function () use ($actor, $seasonVehicle, $active, $request): TransportSeasonVehicle {
            /** @var TransportSeasonVehicle $locked */
            $locked = TransportSeasonVehicle::query()->whereKey($seasonVehicle->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->is_active !== $active) {
                $locked->update(['is_active' => $active]);

                $this->audit->record(
                    $active ? AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_ACTIVATED : AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_DEACTIVATED,
                    $actor->id, 'transport_season_vehicle', $locked->id, null, null,
                    [
                        'before_values' => ['is_active' => ! $active],
                        'after_values' => ['is_active' => $active],
                        'metadata' => ['season_id' => $locked->season_id],
                    ],
                    $request,
                );
            }

            return SeasonVehicleLoader::load($locked);
        });
    }
}
