<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\InventoryBalanceLocker;
use App\Modules\Inventory\Support\InventoryQuantity;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use App\Modules\Notifications\Support\NotificationHooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdjustStock
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryReferenceValidator $references,
        private readonly InventoryBalanceLocker $locker,
        private readonly CorrelationId $correlationId,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): InventoryMovement
    {
        $tenant = $this->tenantContext->require();
        $quantity = InventoryQuantity::validatePositive($data['quantity'] ?? null);
        $reason = $this->references->assertReason((string) ($data['reason'] ?? ''), isAdjustment: true);

        $directionValue = (string) ($data['direction'] ?? '');
        $direction = MovementDirection::tryFrom($directionValue);
        if ($direction === null) {
            throw InventoryDomainException::invalidQuantity();
        }

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $quantity, $reason, $direction): InventoryMovement {
            $warehouse = $this->references->resolveActiveWarehouse((int) $data['warehouse_id']);
            $item = $this->references->resolveActiveItem((int) $data['inventory_item_id']);

            $balance = $this->locker->ensureAndLock((int) $warehouse->id, (int) $item->id);
            $before = InventoryQuantity::format((string) $balance->on_hand);
            $after = $direction === MovementDirection::In
                ? InventoryQuantity::applyInbound($before, $quantity)
                : InventoryQuantity::applyOutbound($before, $quantity);

            $movement = new InventoryMovement([
                'type' => MovementType::Adjustment,
                'warehouse_id' => $warehouse->id,
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'direction' => $direction,
                'balance_before' => $before,
                'balance_after' => $after,
                'transfer_group_id' => null,
                'reason' => $reason,
                'reference' => isset($data['reference']) && $data['reference'] !== ''
                    ? (string) $data['reference']
                    : null,
                'performed_by' => $actor->id,
                'occurred_at' => now(),
                'correlation_id' => $this->correlationId->get(),
            ]);
            $movement->save();

            $balance->on_hand = $after;
            $balance->save();

            $this->security->record(AuthorizationSecurityEvent::STOCK_ADJUSTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'movement_id' => $movement->id,
                'movement_number' => $movement->movement_number,
                'warehouse_id' => $warehouse->id,
                'warehouse_number' => $warehouse->warehouse_number,
                'inventory_item_id' => $item->id,
                'item_number' => $item->item_number,
                'quantity' => $quantity,
                'direction' => $direction->value,
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $reason,
            ], $request);

            if ($direction === MovementDirection::Out) {
                $this->notifications->stockBelowMinimum($warehouse, $item, $after);
            }

            return $movement->load(['warehouse', 'item', 'performer']);
        });
    }
}
