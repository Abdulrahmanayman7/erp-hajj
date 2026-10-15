<?php

namespace App\Modules\SeasonPilgrims\Actions\FamilyGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Only an empty family group may be deleted; members are never detached
 * implicitly.
 */
final class DeleteFamilyGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonResourceDeletionReferences $deletionReferences,
    ) {}

    public function execute(User $actor, SeasonFamilyGroup $group, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $group, $request, $tenant): void {
            /** @var SeasonFamilyGroup $locked */
            $locked = SeasonFamilyGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->members()->exists()) {
                throw SeasonPilgrimDomainException::familyGroupNotEmpty();
            }

            $this->deletionReferences->assertDeletable($locked);

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_family_group_id' => $locked->id,
                'season_family_group_name' => $locked->name,
                'metadata' => ['season_id' => $locked->season_id],
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_FAMILY_GROUP_DELETED, $context, $request);
        });
    }
}
