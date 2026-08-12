<?php

namespace App\Modules\Dashboard\Support;

use App\Core\Tenancy\TenantContext;
use Carbon\CarbonInterface;

final class DashboardClock
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function timezone(): string
    {
        $tenant = $this->tenantContext->require();

        return $tenant->timezone ?: (string) config('app.timezone', 'Asia/Riyadh');
    }

    public function now(): CarbonInterface
    {
        return now($this->timezone());
    }

    /** Tenant calendar "today" at start of day. */
    public function today(): CarbonInterface
    {
        return $this->now()->copy()->startOfDay();
    }

    public function todayDate(): string
    {
        return $this->today()->toDateString();
    }

    public function generatedAtIso(): string
    {
        return $this->now()->toIso8601String();
    }
}
