<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\RunCancellation;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelRun
{
    public function __construct(
        private readonly RunCancellation $cancellation,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportVehicleRun $run, string $reason, Request $request): TransportVehicleRun
    {
        return DB::transaction(function () use ($actor, $run, $reason, $request): TransportVehicleRun {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            $from = $locked->status;
            $cancelledPassengers = $this->cancellation->cancel($locked, $actor->id, $reason);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_CANCELLED, $actor->id, 'transport_vehicle_run', $locked->id, $movement->movement_number.'/'.$locked->run_number, null, [
                'from_status' => $from->value,
                'to_status' => RunStatus::Cancelled->value,
                'reason' => $reason,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'transport_movement_id' => $movement->id,
                    'cancelled_passengers' => $cancelledPassengers,
                ],
            ], $request);

            return RunLoader::load($locked);
        });
    }
}
