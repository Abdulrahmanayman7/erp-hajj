<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F15: hard delete only for a Draft season that was never activated and
 * holds no season data. Never cascades.
 */
final class DeleteSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonResourceDeletionReferences $deletionReferences,
    ) {}

    public function execute(User $actor, Season $season, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $season, $request, $tenant): void {
            /** @var Season $locked */
            $locked = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();

            $hasData = $locked->seasonPilgrims()->exists()
                || $locked->programs()->exists()
                || $locked->familyGroups()->exists()
                || $locked->pilgrimGroups()->exists();

            if ($locked->status !== SeasonStatus::Draft || $locked->first_activated_at !== null || $hasData) {
                throw SeasonPilgrimDomainException::seasonDeleteForbidden();
            }

            $this->deletionReferences->assertDeletable($locked);

            DB::table('season_pilgrim_reference_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('season_id', $locked->id)
                ->delete();

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->id,
                'season_code' => $locked->code,
                'season_name' => $locked->name,
                'from_status' => SeasonStatus::Draft->value,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_DELETED, $context, $request);
        });
    }
}
