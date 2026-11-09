<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Dual page permissions required for the executive dashboard (S10-D02).
 *
 * @return list<string>
 */
function execDashPagePermissions(): array
{
    return ['executive_dashboard.view', 'operational_kpis.view'];
}

/**
 * @param  list<string>  $extra
 * @return list<string>
 */
function execDashPermissions(array $extra = []): array
{
    return array_values(array_unique(array_merge(execDashPagePermissions(), $extra)));
}

/**
 * @param  list<string>  $permissionNames
 */
function execDashActingAs(array $permissionNames, Tenant $tenant): User
{
    return kpiActingAsUserWith($permissionNames, $tenant);
}

function execDashGet(int|string|null $seasonId = null): TestResponse
{
    $uri = '/api/v1/executive-dashboard';

    if ($seasonId !== null) {
        $uri .= '?season_id='.$seasonId;
    }

    return spaGetJson($uri);
}

/**
 * @return array<string, array<string, mixed>>
 */
function execDashKpiMap(TestResponse $response): array
{
    return kpiMap($response);
}
