<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Planned / Boarding only. The capacity override must stay within the
 * current SeasonVehicle capacity and not drop below the occupied count
 * (T5).
 */
final class UpdateRun
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportVehicleRun $run, array $data, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $data, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            if (! in_array($locked->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
                throw TransportationDomainException::runNotEditable();
            }

            $changes = Arr::only($data, ['capacity_override', 'notes']);

            if (array_key_exists('capacity_override', $changes) && $changes['capacity_override'] !== null) {
                $override = (int) $changes['capacity_override'];
                $seasonVehicleId = (int) $locked->currentVehicleAssignment()->value('transport_season_vehicle_id');
                $capacity = (int) TransportSeasonVehicle::query()->whereKey($seasonVehicleId)->lockForUpdate()->value('operational_capacity');

                if ($override > $capacity || $override < $locked->occupiedCount()) {
                    throw TransportationDomainException::runCapacityOverrideInvalid();
                }

                $changes['capacity_override'] = $override;
            }

            $before = $locked->only(array_keys($changes));
            $locked->update($changes);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_UPDATED, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'before_values' => $before,
                'after_values' => $changes,
                'metadata' => ['season_id' => $locked->season_id, 'transport_movement_id' => $movement->id],
            ], $request);

            return RunLoader::load($locked);
        });
    }
}
