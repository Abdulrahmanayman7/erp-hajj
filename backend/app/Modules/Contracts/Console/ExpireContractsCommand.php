<?php

namespace App\Modules\Contracts\Console;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Contracts\Actions\TransitionContract;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use Illuminate\Console\Command;

class ExpireContractsCommand extends Command
{
    protected $signature = 'contracts:expire';

    protected $description = 'Expire executing contracts whose end_date is before today (per tenant timezone)';

    public function handle(TenantContext $tenantContext, TransitionContract $transition): int
    {
        $expiredCount = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $transition, &$expiredCount): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $transition, &$expiredCount): void {
                        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
                        $today = now($timezone)->toDateString();

                        Contract::query()
                            ->where('status', ContractStatus::Executing)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '<', $today)
                            ->orderBy('id')
                            ->chunkById(100, function ($contracts) use ($transition, &$expiredCount): void {
                                foreach ($contracts as $contract) {
                                    try {
                                        $transition->execute(
                                            null,
                                            $contract,
                                            ContractStatus::Expired,
                                            null,
                                            AuthorizationSecurityEvent::CONTRACT_EXPIRED,
                                            null,
                                            ['source' => 'scheduler'],
                                        );
                                        $expiredCount++;
                                    } catch (ContractDomainException) {
                                        // Idempotent: already transitioned or no longer eligible.
                                    }
                                }
                            });
                    });
                }
            });

        $this->info("Expired {$expiredCount} contract(s).");

        return self::SUCCESS;
    }
}
