<?php

namespace App\Modules\Transportation\Actions\Passengers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Support\PassengerCancellation;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportTransaction;
use Illuminate\Http\Request;

/**
 * Assigned → Cancelled while the Run is Planned / Boarding (T8). Releases
 * capacity; the row stays.
 */
final class CancelPassenger
{
    public function __construct(
        private readonly PassengerCancellation $cancellation,
        private readonly TransportAudit $audit,
    ) {}

    public function execute(User $actor, TransportRunPassenger $passenger, string $reason, Request $request): TransportRunPassenger
    {
        return TransportTransaction::run(function () use ($actor, $passenger, $reason, $request): TransportRunPassenger {
            [$movement, $run] = RunLocks::lock($passenger->vehicle_run_id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            /** @var TransportRunPassenger $locked */
            $locked = TransportRunPassenger::query()->whereKey($passenger->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PassengerStatus::Assigned || ! in_array($run->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
                throw TransportationDomainException::passengerInvalidTransition([$locked->id]);
            }

            $this->cancellation->cancel($run, $locked, $actor->id, $reason, ['cause' => 'manual']);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_CANCELLED, $actor->id, 'transport_run_passenger', $locked->id, $movement->movement_number.'/'.$run->run_number, null, [
                'from_status' => PassengerStatus::Assigned->value,
                'to_status' => PassengerStatus::Cancelled->value,
                'reason' => $reason,
                'metadata' => ['season_id' => $run->season_id, 'vehicle_run_id' => $run->id, 'season_pilgrim_id' => $locked->season_pilgrim_id],
            ], $request);

            return PassengerLoader::load($locked);
        });
    }
}
