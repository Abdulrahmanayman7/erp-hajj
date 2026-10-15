<?php

namespace App\Modules\SeasonPilgrims\Actions\FamilyGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Designates the family group's primary member (F9). Does not change any
 * member's lifecycle (F30).
 */
final class SetFamilyPrimaryMember
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonFamilyGroup $group, int $seasonPilgrimId, Request $request): SeasonFamilyGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $seasonPilgrimId, $request, $tenant): SeasonFamilyGroup {
            /** @var SeasonFamilyGroup $locked */
            $locked = SeasonFamilyGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            /** @var SeasonPilgrim|null $member */
            $member = $locked->members()->whereKey($seasonPilgrimId)->lockForUpdate()->first();
            if ($member === null) {
                throw SeasonPilgrimDomainException::familyMemberInvalid();
            }

            /** @var SeasonPilgrim|null $current */
            $current = $locked->members()->where('is_family_primary', true)->lockForUpdate()->first();

            if ($current === null || ! $current->is($member)) {
                $current?->update(['is_family_primary' => false]);
                $member->update(['is_family_primary' => true]);

                $this->security->record(AuthorizationSecurityEvent::SEASON_FAMILY_PRIMARY_CHANGED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_family_group_id' => $locked->id,
                    'season_family_group_name' => $locked->name,
                    'before_values' => ['primary_season_pilgrim_id' => $current?->id],
                    'after_values' => ['primary_season_pilgrim_id' => $member->id],
                    'metadata' => ['season_id' => $locked->season_id],
                ], $request);
            }

            return $locked->load(['members.pilgrim'])->loadCount('members');
        });
    }
}
