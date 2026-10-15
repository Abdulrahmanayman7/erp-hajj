<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonReferenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F28/F29: the pilgrim group may be set, changed or cleared only while the
 * SeasonPilgrim is Registered; it is immutable once Active.
 */
final class AssignSeasonPilgrimGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonReferenceResolver $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, ?int $groupId, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $groupId, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            /** @var Season $season */
            $season = $locked->season()->firstOrFail();
            SeasonGuard::assertWritable($season);

            if ($locked->status !== SeasonPilgrimStatus::Registered) {
                throw SeasonPilgrimDomainException::pilgrimGroupLocked();
            }

            $group = $groupId !== null ? $this->references->pilgrimGroup($season, $groupId) : null;
            $fromId = $locked->season_pilgrim_group_id !== null ? (int) $locked->season_pilgrim_group_id : null;

            $locked->update(['season_pilgrim_group_id' => $group?->id]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_GROUP_ASSIGNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'before_values' => ['season_pilgrim_group_id' => $fromId],
                'after_values' => ['season_pilgrim_group_id' => $group?->id],
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
