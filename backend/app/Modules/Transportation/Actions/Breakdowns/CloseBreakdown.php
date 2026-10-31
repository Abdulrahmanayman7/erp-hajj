<?php

namespace App\Modules\Transportation\Actions\Breakdowns;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CloseBreakdown
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportVehicleBreakdown $breakdown, string $reason, Request $request): TransportVehicleBreakdown
    {
        return DB::transaction(function () use ($actor, $breakdown, $reason, $request): TransportVehicleBreakdown {
            /** @var TransportVehicleBreakdown $locked */
            $locked = TransportVehicleBreakdown::query()->whereKey($breakdown->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== BreakdownStatus::Open) {
                throw TransportationDomainException::breakdownNotOpen();
            }

            [$movement] = RunLocks::lock($locked->vehicle_run_id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            $locked->update([
                'status' => BreakdownStatus::Closed,
                'closed_at' => Carbon::now(),
                'closed_by' => $actor->id,
                'closure_reason' => trim($reason),
            ]);

            $this->audit->record(
                AuthorizationSecurityEvent::TRANSPORT_BREAKDOWN_CLOSED,
                $actor->id,
                'transport_vehicle_breakdown',
                $locked->id,
                $movement->movement_number,
                null,
                [
                    'from_status' => BreakdownStatus::Open->value,
                    'to_status' => BreakdownStatus::Closed->value,
                    'reason' => trim($reason),
                    'metadata' => ['season_id' => $locked->season_id],
                ],
                $request,
            );

            return $locked->fresh();
        }, 5);
    }
}
