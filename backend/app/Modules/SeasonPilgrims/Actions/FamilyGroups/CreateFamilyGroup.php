<?php

namespace App\Modules\SeasonPilgrims\Actions\FamilyGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateFamilyGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonFamilyGroup
    {
        $tenant = $this->tenantContext->require();
        SeasonGuard::assertWritable($season);

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonFamilyGroup {
            $group = SeasonFamilyGroup::query()->create([
                'season_id' => $season->id,
                'name' => trim((string) $data['name']),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_FAMILY_GROUP_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_family_group_id' => $group->id,
                'season_family_group_name' => $group->name,
                'metadata' => ['season_id' => $season->id],
            ], $request);

            return $group->loadCount('members');
        });
    }
}
