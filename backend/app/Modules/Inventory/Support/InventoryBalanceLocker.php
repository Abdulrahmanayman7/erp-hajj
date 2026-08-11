<?php

namespace App\Modules\Inventory\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryBalance;
use Illuminate\Database\QueryException;

/**
 * Race-safe balance row ensure + FOR UPDATE locking (ADR-0011).
 */
final class InventoryBalanceLocker
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function ensureAndLock(int $warehouseId, int $itemId): InventoryBalance
    {
        $tenantId = (int) $this->tenantContext->require()->id;

        $existing = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $created = new InventoryBalance;
            $created->forceFill([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'inventory_item_id' => $itemId,
                'on_hand' => '0.000',
            ]);
            $created->save();
        } catch (QueryException) {
            // Concurrent insert won — fall through to lock.
        }

        $locked = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw InventoryDomainException::insufficientStock();
        }

        return $locked;
    }

    /**
     * Lock source and destination balances in warehouse_id ASC order (same item).
     *
     * @return array{0: InventoryBalance, 1: InventoryBalance} [source, destination]
     */
    public function lockPairForTransfer(int $sourceWarehouseId, int $destinationWarehouseId, int $itemId): array
    {
        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw InventoryDomainException::transferSameWarehouse();
        }

        $ordered = [$sourceWarehouseId, $destinationWarehouseId];
        sort($ordered, SORT_NUMERIC);

        $first = $this->ensureAndLock($ordered[0], $itemId);
        $second = $this->ensureAndLock($ordered[1], $itemId);

        $byWarehouse = [
            (int) $first->warehouse_id => $first,
            (int) $second->warehouse_id => $second,
        ];

        return [
            $byWarehouse[$sourceWarehouseId],
            $byWarehouse[$destinationWarehouseId],
        ];
    }
}
