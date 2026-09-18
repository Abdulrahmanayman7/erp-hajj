<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Inventory\Enums\StockState;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Support\StockStateResolver;
use App\Modules\Notifications\Support\NotificationHooks;
use Illuminate\Console\Command;

class ScanLowStockCommand extends Command
{
    protected $signature = 'notifications:scan-low-stock';

    protected $description = 'Notify warehouse responsible users about low/out stock (daily bucket)';

    public function handle(TenantContext $tenantContext, NotificationHooks $hooks): int
    {
        $count = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $hooks, &$count): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($hooks, &$count): void {
                        InventoryBalance::query()
                            ->with(['warehouse', 'item'])
                            ->orderBy('id')
                            ->chunkById(100, function ($balances) use ($hooks, &$count): void {
                                foreach ($balances as $balance) {
                                    /** @var InventoryBalance $balance */
                                    $warehouse = $balance->warehouse;
                                    $item = $balance->item;
                                    if ($warehouse === null || $item === null) {
                                        continue;
                                    }

                                    $state = StockStateResolver::resolve(
                                        (string) $balance->on_hand,
                                        (string) $item->minimum_stock,
                                    );

                                    if (! in_array($state, [StockState::Low, StockState::OutOfStock], true)) {
                                        continue;
                                    }

                                    $hooks->stockBelowMinimum($warehouse, $item, (string) $balance->on_hand);
                                    $count++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned low stock; candidates={$count}.");

        return self::SUCCESS;
    }
}
