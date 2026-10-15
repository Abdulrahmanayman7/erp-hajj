<?php

namespace App\Modules\Transportation\Actions\Entitlements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Passenger rows keep their entitlement snapshot, so deletion is allowed.
 */
final class DeleteEntitlement
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportEntitlement $entitlement, Request $request): void
    {
        DB::transaction(function () use ($actor, $entitlement, $request): void {
            /** @var TransportEntitlement $locked */
            $locked = TransportEntitlement::query()->whereKey($entitlement->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $before = TransportAudit::values($locked->only(['vehicle_class', 'service_level']));
            $locked->delete();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_ENTITLEMENT_DELETED, $actor->id, 'transport_entitlement', $locked->id, null, null, [
                'before_values' => $before,
                'metadata' => ['season_id' => $locked->season_id, 'season_program_id' => $locked->season_program_id],
            ], $request);
        });
    }
}
