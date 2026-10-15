<?php

namespace App\Modules\SeasonPilgrims\Context;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Closure;
use LogicException;

/**
 * In-memory holder of "which season is this unit of work operating on"
 * (F35). Parallels TenantContext but never replaces it: a season can only
 * be set when it belongs to the current tenant. Registered as a scoped
 * singleton — fresh per request and per queued job.
 */
class SeasonContext
{
    private ?Season $season = null;

    private bool $explicit = false;

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function set(Season $season, bool $explicit = false): void
    {
        $this->assertSameTenant($season);
        $this->season = $season;
        $this->explicit = $explicit;
    }

    public function get(): ?Season
    {
        return $this->season;
    }

    public function id(): ?int
    {
        return $this->season?->id;
    }

    public function has(): bool
    {
        return $this->season !== null;
    }

    /**
     * True when the client explicitly selected the season; false when the
     * tenant's Active season was used as the default.
     */
    public function isExplicit(): bool
    {
        return $this->explicit;
    }

    public function require(): Season
    {
        return $this->season ?? throw SeasonPilgrimDomainException::seasonContextMissing();
    }

    public function clear(): void
    {
        $this->season = null;
        $this->explicit = false;
    }

    /**
     * Run the closure in the given season's context, restoring the previous
     * state even when the closure throws.
     */
    public function runInSeason(Season $season, Closure $callback): mixed
    {
        $this->assertSameTenant($season);

        $previous = [$this->season, $this->explicit];
        $this->season = $season;
        $this->explicit = true;

        try {
            return $callback();
        } finally {
            [$this->season, $this->explicit] = $previous;
        }
    }

    private function assertSameTenant(Season $season): void
    {
        $tenant = $this->tenantContext->require();

        if ((int) $season->tenant_id !== (int) $tenant->id) {
            throw new LogicException('Season context must belong to the current tenant.');
        }
    }
}
