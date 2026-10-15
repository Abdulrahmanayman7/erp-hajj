<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A pilgrim group may be deleted only when it has no members and no
 * supervisor history (history is preserved, F27).
 */
final class DeletePilgrimGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonResourceDeletionReferences $deletionReferences,
    ) {}

    public function execute(User $actor, SeasonPilgrimGroup $group, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $group, $request, $tenant): void {
            /** @var SeasonPilgrimGroup $locked */
            $locked = SeasonPilgrimGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->seasonPilgrims()->exists() || $locked->supervisors()->exists()) {
                throw SeasonPilgrimDomainException::pilgrimGroupInUse();
            }

            $this->deletionReferences->assertDeletable($locked);

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_group_id' => $locked->id,
                'season_pilgrim_group_code' => $locked->code,
                'season_pilgrim_group_name' => $locked->name,
                'metadata' => ['season_id' => $locked->season_id],
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_GROUP_DELETED, $context, $request);
        });
    }
}
