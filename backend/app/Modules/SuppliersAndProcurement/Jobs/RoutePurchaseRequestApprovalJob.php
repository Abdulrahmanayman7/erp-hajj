<?php

namespace App\Modules\SuppliersAndProcurement\Jobs;

use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\Jobs\TenantAware;
use App\Modules\SuppliersAndProcurement\Actions\RoutePurchaseRequestApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * After-commit routing worker (EC-01). Config blocks succeed; technical failures retry.
 */
final class RoutePurchaseRequestApprovalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAware;

    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        public int $purchaseRequestId,
        public int $versionNo,
        public ?string $correlationId = null,
    ) {
        $this->captureTenantContext();
    }

    public function handle(RoutePurchaseRequestApproval $action, CorrelationId $correlationId): void
    {
        if ($this->correlationId !== null && $this->correlationId !== '') {
            $correlationId->set($this->correlationId);
        }

        $action->execute($this->purchaseRequestId, $this->versionNo);
    }
}
