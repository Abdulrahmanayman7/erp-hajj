<?php

/**
 * S11-P1 cross-module integration helpers.
 *
 * Reuses module helpers already loaded from Pest.php (Transportation, Accommodation,
 * OperationalKpis, ExecutiveDashboard, SeasonalTemplates, RisksAndComplaints, SeasonProjects).
 */

use App\Core\Tenancy\Models\Tenant;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use Illuminate\Support\Collection;

/**
 * @return array{opened: int, refreshed: int, cleared: int}
 */
function s11ScanAlerts(Tenant $tenant): array
{
    return withTenant($tenant, fn (): array => app(ExecutiveAlertScanner::class)->scanCurrentTenant());
}

/**
 * @return Collection<int, ExecutiveAlert>
 */
function s11Alerts(Tenant $tenant, ?ExecutiveAlertRule $rule = null): Collection
{
    return withTenant($tenant, function () use ($rule) {
        $query = ExecutiveAlert::query()->orderBy('id');
        if ($rule !== null) {
            $query->where('rule_code', $rule->value);
        }

        return $query->get();
    });
}
