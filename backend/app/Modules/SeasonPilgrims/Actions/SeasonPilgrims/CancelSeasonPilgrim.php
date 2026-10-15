<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Events\SeasonPilgrimCancelled;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registered|Active → Cancelled (F7, F33). Records reason, actor and
 * timestamp; keeps the Pilgrim, snapshot, program history and memberships;
 * emits SeasonPilgrimCancelled after commit. Never mutates other domains.
 */
final class CancelSeasonPilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CorrelationId $correlationId,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, string $reason, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $reason, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());
            SeasonGuard::assertNotTerminal($locked);

            $previous = $locked->status;
            $now = now();

            $locked->update([
                'status' => SeasonPilgrimStatus::Cancelled,
                'cancelled_at' => $now,
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_CANCELLED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'from_status' => $previous->value,
                'to_status' => SeasonPilgrimStatus::Cancelled->value,
                'reason' => $reason,
                'metadata' => ['season_id' => $locked->season_id, 'pilgrim_id' => $locked->pilgrim_id],
            ], $request);

            event(new SeasonPilgrimCancelled(
                tenantId: (int) $tenant->id,
                seasonId: (int) $locked->season_id,
                seasonPilgrimId: (int) $locked->id,
                pilgrimId: (int) $locked->pilgrim_id,
                previousStatus: $previous->value,
                reason: $reason,
                cancelledBy: $actor->id,
                cancelledAt: $now->toIso8601String(),
                correlationId: $this->correlationId->get(),
            ));

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
