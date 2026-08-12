<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use Illuminate\Console\Command;

class ScanCustodiesCommand extends Command
{
    protected $signature = 'notifications:scan-custodies';

    protected $description = 'Notify about custody expected-return-soon and overdue (daily bucket)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
    ): int {
        $soonCount = 0;
        $overdueCount = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $dispatcher, $recipients, &$soonCount, &$overdueCount): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $dispatcher, $recipients, &$soonCount, &$overdueCount): void {
                        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
                        $now = now($timezone);
                        $bucket = $now->toDateString();
                        $days = max(0, (int) config('notifications.custody_expected_return_soon_days', 3));
                        $until = $now->copy()->addDays($days);

                        AssetCustody::query()
                            ->where('status', CustodyStatus::Active)
                            ->whereNotNull('expected_return_at')
                            ->where('expected_return_at', '>=', $now)
                            ->where('expected_return_at', '<=', $until)
                            ->orderBy('id')
                            ->chunkById(100, function ($custodies) use ($dispatcher, $recipients, $bucket, &$soonCount): void {
                                foreach ($custodies as $custody) {
                                    /** @var AssetCustody $custody */
                                    $user = $recipients->resolveUserFromEmployeeId((int) $custody->employee_id);
                                    if ($user === null) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::CustodyExpectedReturnSoon,
                                        recipientUsers: [$user],
                                        entityType: 'asset',
                                        entityId: (int) $custody->asset_id,
                                        dedupeBucket: $bucket,
                                        context: ['number' => $custody->custody_number],
                                    );
                                    $soonCount++;
                                }
                            });

                        AssetCustody::query()
                            ->where('status', CustodyStatus::Active)
                            ->whereNotNull('expected_return_at')
                            ->where('expected_return_at', '<', $now)
                            ->orderBy('id')
                            ->chunkById(100, function ($custodies) use ($dispatcher, $recipients, $bucket, &$overdueCount): void {
                                foreach ($custodies as $custody) {
                                    /** @var AssetCustody $custody */
                                    $user = $recipients->resolveUserFromEmployeeId((int) $custody->employee_id);
                                    if ($user === null) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::CustodyOverdue,
                                        recipientUsers: [$user],
                                        entityType: 'asset',
                                        entityId: (int) $custody->asset_id,
                                        dedupeBucket: $bucket,
                                        context: ['number' => $custody->custody_number],
                                    );
                                    $overdueCount++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned custodies; soon={$soonCount}, overdue={$overdueCount}.");

        return self::SUCCESS;
    }
}
