<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use Illuminate\Console\Command;

class ScanContractsCommand extends Command
{
    protected $signature = 'notifications:scan-contracts';

    protected $description = 'Notify about contracts expiring soon (daily bucket)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
    ): int {
        $count = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $dispatcher, $recipients, &$count): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $dispatcher, $recipients, &$count): void {
                        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
                        $today = now($timezone)->startOfDay();
                        $days = max(0, (int) config('contracts.expiring_soon_days', 30));
                        $until = $today->copy()->addDays($days)->toDateString();
                        $bucket = $today->toDateString();

                        Contract::query()
                            ->where('status', ContractStatus::Executing)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $today->toDateString())
                            ->whereDate('end_date', '<=', $until)
                            ->orderBy('id')
                            ->chunkById(100, function ($contracts) use ($dispatcher, $recipients, $bucket, &$count): void {
                                foreach ($contracts as $contract) {
                                    /** @var Contract $contract */
                                    $ids = [(int) $contract->created_by];
                                    $employeeUser = $recipients->resolveUserFromEmployeeId($contract->employee_id);
                                    if ($employeeUser !== null) {
                                        $ids[] = (int) $employeeUser->id;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::ContractExpiringSoon,
                                        recipientUsers: $ids,
                                        entityType: 'contract',
                                        entityId: (int) $contract->id,
                                        dedupeBucket: $bucket,
                                        context: ['number' => $contract->contract_number],
                                    );
                                    $count++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned contracts; dispatched {$count} expiring-soon notification set(s).");

        return self::SUCCESS;
    }
}
