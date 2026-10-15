<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePilgrimGroup
{
    private const FIELDS = ['code', 'name', 'notes'];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonPilgrimGroup $group, array $data, Request $request): SeasonPilgrimGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $data, $request, $tenant): SeasonPilgrimGroup {
            /** @var SeasonPilgrimGroup $locked */
            $locked = SeasonPilgrimGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if (isset($changes['code'])) {
                $changes['code'] = trim((string) $changes['code']);
                $taken = SeasonPilgrimGroup::query()
                    ->where('season_id', $locked->season_id)
                    ->where('code', $changes['code'])
                    ->whereKeyNot($locked->id)
                    ->exists();
                if ($taken) {
                    throw SeasonPilgrimDomainException::pilgrimGroupCodeTaken();
                }
            }

            $before = $locked->only(self::FIELDS);
            $locked->update($changes);
            $after = $locked->only(self::FIELDS);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_GROUP_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_group_id' => $locked->id,
                'season_pilgrim_group_code' => $locked->code,
                'season_pilgrim_group_name' => $locked->name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return PilgrimGroupLoader::load($locked);
        });
    }
}
