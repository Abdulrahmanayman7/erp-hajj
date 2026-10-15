<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonReferenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Adds, moves or removes a SeasonPilgrim's (optional) family group
 * membership (F9). Membership never makes anyone primary: the primary
 * member is chosen only via SetFamilyPrimaryMember. A primary member who
 * leaves loses the designation and the group has no primary until one is
 * chosen explicitly.
 */
final class SetSeasonPilgrimFamilyGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonReferenceResolver $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, ?int $familyGroupId, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $familyGroupId, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            /** @var Season $season */
            $season = $locked->season()->firstOrFail();
            SeasonGuard::assertWritable($season);
            SeasonGuard::assertNotTerminal($locked);

            $fromId = $locked->season_family_group_id !== null ? (int) $locked->season_family_group_id : null;
            if ($fromId === $familyGroupId) {
                return SeasonPilgrimLoader::load($locked);
            }

            if ($fromId !== null) {
                $this->references->familyGroup($season, $fromId, lock: true);
            }
            $target = $familyGroupId !== null ? $this->references->familyGroup($season, $familyGroupId, lock: true) : null;

            $wasPrimary = (bool) $locked->is_family_primary;

            $locked->update([
                'season_family_group_id' => $target?->id,
                'is_family_primary' => false,
            ]);

            $event = match (true) {
                $fromId === null => AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_ADDED,
                $target === null => AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_REMOVED,
                default => AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_MOVED,
            };

            $this->security->record($event, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'before_values' => ['season_family_group_id' => $fromId, 'is_family_primary' => $wasPrimary],
                'after_values' => ['season_family_group_id' => $target?->id, 'is_family_primary' => false],
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'primary_cleared_for_family_group_id' => $wasPrimary ? $fromId : null,
                ],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
