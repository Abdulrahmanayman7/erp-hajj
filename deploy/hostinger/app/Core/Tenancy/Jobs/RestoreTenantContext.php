<?php

namespace App\Core\Tenancy\Jobs;

use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Job middleware: re-verifies the tenant at execution time (state may have
 * changed between dispatch and execution) and runs handle() inside
 * runAsTenant(), which restores the previous (empty) context in a finally
 * block — long-running workers never leak context between jobs.
 *
 * Non-active tenant behavior (docs/02-architecture/MULTI_TENANCY.md §9):
 * - archived     → cancel (delete) the job and log; never process.
 * - not active   → business jobs are released for retry;
 *                  TenantMaintenanceJob implementations still run.
 * - tenant gone  → fail loudly (registry corruption).
 */
class RestoreTenantContext
{
    private const RELEASE_DELAY_SECONDS = 300;

    public function handle(object $job, Closure $next): void
    {
        $tenant = Tenant::query()->find($job->tenantId);

        if ($tenant === null) {
            $job->fail(new InvalidTenantContextException(
                "Queued job references a missing tenant [{$job->tenantId}].",
            ));

            return;
        }

        if ($tenant->status === TenantStatus::Archived) {
            Log::warning('Cancelling queued job for archived tenant.', [
                'job' => $job::class,
                'tenant_id' => $tenant->id,
            ]);

            $job->delete();

            return;
        }

        if (! $tenant->status->isActive() && ! $job instanceof TenantMaintenanceJob) {
            $job->release(self::RELEASE_DELAY_SECONDS);

            return;
        }

        app(TenantContext::class)->runAsTenant($tenant, fn () => $next($job));
    }
}
