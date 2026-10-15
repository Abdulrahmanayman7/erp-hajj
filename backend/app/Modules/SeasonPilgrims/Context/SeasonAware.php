<?php

namespace App\Modules\SeasonPilgrims\Context;

use App\Core\Tenancy\Jobs\TenantAware;

/**
 * For queued jobs that operate on one season of one tenant (F35). Captures
 * tenant_id and season_id at dispatch time as server-generated metadata;
 * RestoreTenantContext then RestoreSeasonContext rebuild both contexts
 * before handle() runs.
 *
 * Jobs using this trait MUST call captureSeasonContext() in their
 * constructor — dispatching without tenant and season context fails.
 */
trait SeasonAware
{
    use TenantAware {
        middleware as tenantMiddleware;
    }

    public ?int $seasonId = null;

    public function captureSeasonContext(): void
    {
        $this->captureTenantContext();
        $this->seasonId = app(SeasonContext::class)->require()->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [...$this->tenantMiddleware(), new RestoreSeasonContext];
    }
}
