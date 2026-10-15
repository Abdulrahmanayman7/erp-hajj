<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreatePilgrimGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonPilgrimGroup
    {
        $tenant = $this->tenantContext->require();
        SeasonGuard::assertWritable($season);

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonPilgrimGroup {
            $code = trim((string) $data['code']);
            if (SeasonPilgrimGroup::query()->where('season_id', $season->id)->where('code', $code)->exists()) {
                throw SeasonPilgrimDomainException::pilgrimGroupCodeTaken();
            }

            try {
                $group = SeasonPilgrimGroup::query()->create([
                    'season_id' => $season->id,
                    'code' => $code,
                    'name' => trim((string) $data['name']),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SeasonPilgrimDomainException::pilgrimGroupCodeTaken();
            }

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_GROUP_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_group_id' => $group->id,
                'season_pilgrim_group_code' => $group->code,
                'season_pilgrim_group_name' => $group->name,
                'metadata' => ['season_id' => $season->id],
            ], $request);

            return PilgrimGroupLoader::load($group);
        });
    }
}
