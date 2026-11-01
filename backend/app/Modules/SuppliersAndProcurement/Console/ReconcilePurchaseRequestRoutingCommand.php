<?php

namespace App\Modules\SuppliersAndProcurement\Console;

use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Jobs\RoutePurchaseRequestApprovalJob;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Redispatch stranded submitted PRs that lack an approval instance for current version_no (EC-01).
 */
class ReconcilePurchaseRequestRoutingCommand extends Command
{
    protected $signature = 'purchase-requests:reconcile-routing
                            {--min-age-seconds=60 : Only PRs submitted at least this many seconds ago}
                            {--dry-run : List stranded PRs without dispatching}';

    protected $description = 'Redispatch routing for submitted purchase requests missing an approval instance';

    public function handle(TenantContext $tenantContext, CorrelationId $correlationId): int
    {
        $minAge = max(0, (int) $this->option('min-age-seconds'));
        $dryRun = (bool) $this->option('dry-run');
        $dispatched = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $correlationId, $minAge, $dryRun, &$dispatched): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($correlationId, $minAge, $dryRun, &$dispatched): void {
                        $cutoff = now()->subSeconds($minAge);

                        PurchaseRequest::query()
                            ->where('status', PurchaseRequestStatus::Submitted)
                            ->where(function ($q) use ($cutoff): void {
                                $q->whereNull('submitted_at')->orWhere('submitted_at', '<=', $cutoff);
                            })
                            ->orderBy('id')
                            ->chunkById(100, function ($prs) use ($correlationId, $dryRun, &$dispatched): void {
                                foreach ($prs as $pr) {
                                    /** @var PurchaseRequest $pr */
                                    $hasInstance = PurchaseRequestApprovalInstance::query()
                                        ->where('purchase_request_id', $pr->id)
                                        ->where('version_no', (int) $pr->version_no)
                                        ->exists();

                                    if ($hasInstance) {
                                        continue;
                                    }

                                    if ($dryRun) {
                                        $this->line("stranded pr={$pr->id} version={$pr->version_no} code=".($pr->routing_block_code ?? '-'));
                                        $dispatched++;

                                        continue;
                                    }

                                    $corr = $correlationId->get() ?? ('reconcile-pr-route-'.Str::uuid()->toString());
                                    RoutePurchaseRequestApprovalJob::dispatch(
                                        (int) $pr->id,
                                        (int) $pr->version_no,
                                        $corr,
                                    );
                                    $dispatched++;
                                }
                            });
                    });
                }
            });

        $verb = $dryRun ? 'Found' : 'Dispatched';
        $this->info("{$verb} {$dispatched} stranded purchase request(s).");

        return self::SUCCESS;
    }
}
