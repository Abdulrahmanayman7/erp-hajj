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
 * Exceptional Closed → Active (F16): dedicated permission, mandatory
 * reason, audited. Still respects the single-Active-season invariant.
 */
final class ReopenSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonUniqueness $uniqueness,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Season $season, string $reason, Request $request): Season
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $reason, $request, $tenant): Season {
            /** @var Season $locked */
            $locked = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SeasonStatus::Closed) {
                throw SeasonPilgrimDomainException::seasonInvalidTransition();
            }

            ActivationGuard::assertNoOtherActive($locked);

            $previousClosedAt = $locked->closed_at?->toIso8601String();

            $this->uniqueness->guard(fn () => $locked->update([
                'status' => SeasonStatus::Active,
                'activated_at' => now(),
                'activated_by' => $actor->id,
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'closed_at' => null,
                'closed_by' => null,
            ]));

            $this->security->record(AuthorizationSecurityEvent::SEASON_REOPENED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->id,
                'season_code' => $locked->code,
                'season_name' => $locked->name,
                'from_status' => SeasonStatus::Closed->value,
                'to_status' => SeasonStatus::Active->value,
                'reason' => $reason,
                'metadata' => ['previous_closed_at' => $previousClosedAt],
            ], $request);

            return $locked;
        });
    }
}
