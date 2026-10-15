<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonUniqueness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Draft → Active (explicit, F16). At most one Active season per tenant:
 * checked under lock, backed by UNIQUE (tenant_id, active_flag).
 */
final class ActivateSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonUniqueness $uniqueness,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Season $season, Request $request): Season
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $request, $tenant): Season {
            /** @var Season $locked */
            $locked = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SeasonStatus::Draft) {
                throw SeasonPilgrimDomainException::seasonInvalidTransition();
            }

            ActivationGuard::assertNoOtherActive($locked);

            $now = now();
            $this->uniqueness->guard(fn () => $locked->update([
                'status' => SeasonStatus::Active,
                'activated_at' => $now,
                'activated_by' => $actor->id,
                'first_activated_at' => $locked->first_activated_at ?? $now,
            ]));

            $this->security->record(AuthorizationSecurityEvent::SEASON_ACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->id,
                'season_code' => $locked->code,
                'season_name' => $locked->name,
                'from_status' => SeasonStatus::Draft->value,
                'to_status' => SeasonStatus::Active->value,
            ], $request);

            return $locked;
        });
    }
}
