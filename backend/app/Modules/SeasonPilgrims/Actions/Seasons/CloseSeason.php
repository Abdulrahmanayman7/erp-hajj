<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Active → Closed (explicit, F16) after the Season Closing Readiness check
 * (F34): any BLOCKING result prevents closing; WARNINGs are audited.
 */
final class CloseSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonClosingReadiness $readiness,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Season $season, Request $request): Season
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $request, $tenant): Season {
            /** @var Season $locked */
            $locked = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SeasonStatus::Active) {
                throw SeasonPilgrimDomainException::seasonInvalidTransition();
            }

            $report = $this->readiness->evaluate($locked);
            if (! $report['ready']) {
                throw SeasonPilgrimDomainException::seasonClosingBlocked($report['results']);
            }

            $locked->update([
                'status' => SeasonStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_CLOSED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->id,
                'season_code' => $locked->code,
                'season_name' => $locked->name,
                'from_status' => SeasonStatus::Active->value,
                'to_status' => SeasonStatus::Closed->value,
                'metadata' => ['readiness' => $report['results']],
            ], $request);

            return $locked;
        });
    }
}
