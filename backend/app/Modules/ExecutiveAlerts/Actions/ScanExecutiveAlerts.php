<?php

namespace App\Modules\ExecutiveAlerts\Actions;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use Illuminate\Support\Carbon;

final class ScanExecutiveAlerts
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ExecutiveAlertScanner $scanner,
    ) {}

    /**
     * @return array{tenants: int, opened: int, refreshed: int, cleared: int}
     */
    public function scanAllActiveTenants(?Carbon $now = null): array
    {
        $now ??= now();
        $totals = ['tenants' => 0, 'opened' => 0, 'refreshed' => 0, 'cleared' => 0];

        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->runAsTenant($tenant, function () use ($now, &$totals): void {
                $totals['tenants']++;
                $stats = $this->scanner->scanCurrentTenant($now);
                $totals['opened'] += $stats['opened'];
                $totals['refreshed'] += $stats['refreshed'];
                $totals['cleared'] += $stats['cleared'];
            });
        }

        return $totals;
    }
}
