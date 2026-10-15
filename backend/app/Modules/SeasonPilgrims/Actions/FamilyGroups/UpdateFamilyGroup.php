<?php

namespace App\Modules\SeasonPilgrims\Actions\FamilyGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateFamilyGroup
{
    private const FIELDS = ['name', 'notes'];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonFamilyGroup $group, array $data, Request $request): SeasonFamilyGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $data, $request, $tenant): SeasonFamilyGroup {
            /** @var SeasonFamilyGroup $locked */
            $locked = SeasonFamilyGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $before = $locked->only(self::FIELDS);
            $locked->update(array_intersect_key($data, array_flip(self::FIELDS)));
            $after = $locked->only(self::FIELDS);

            $this->security->record(AuthorizationSecurityEvent::SEASON_FAMILY_GROUP_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_family_group_id' => $locked->id,
                'season_family_group_name' => $locked->name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return $locked->load(['members.pilgrim'])->loadCount('members');
        });
    }
}
