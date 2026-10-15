<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Activation\SeasonPilgrimActivationValidator;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registered → Active (F10, F20, F29): requires an Active season and every
 * tagged activation requirement; locks the identity snapshot.
 */
final class ActivateSeasonPilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonPilgrimActivationValidator $validator,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertActive($locked->season()->firstOrFail());
            SeasonGuard::assertRegistered($locked);

            $this->validator->assertSatisfied($locked);

            $now = now();
            $locked->update([
                'status' => SeasonPilgrimStatus::Active,
                'activated_at' => $now,
                'activated_by' => $actor->id,
            ]);

            /** @var SeasonPilgrimIdentitySnapshot $snapshot */
            $snapshot = $locked->snapshot()->lockForUpdate()->firstOrFail();
            $snapshot->update(['locked_at' => $now, 'locked_by' => $actor->id]);

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'pilgrim_name' => $snapshot->full_name,
                'metadata' => ['season_id' => $locked->season_id],
            ];

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_ACTIVATED, [
                ...$context,
                'from_status' => SeasonPilgrimStatus::Registered->value,
                'to_status' => SeasonPilgrimStatus::Active->value,
            ], $request);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_LOCKED, $context, $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
