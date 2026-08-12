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
use Illuminate\Support\Str;

final class TransferStock
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
     * @return array{transfer_out: InventoryMovement, transfer_in: InventoryMovement, transfer_group_id: string}
     */
    public function execute(User $actor, array $data, Request $request): array
    {
        $tenant = $this->tenantContext->require();
        $quantity = InventoryQuantity::validatePositive($data['quantity'] ?? null);
        $reason = $this->references->assertReason((string) ($data['reason'] ?? ''));

        $sourceId = (int) $data['source_warehouse_id'];
        $destId = (int) $data['destination_warehouse_id'];

        if ($sourceId === $destId) {
            throw InventoryDomainException::transferSameWarehouse();
        }

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $quantity, $reason, $sourceId, $destId): array {
            $sourceWarehouse = $this->references->resolveActiveWarehouse($sourceId);
            $destWarehouse = $this->references->resolveActiveWarehouse($destId);
            $item = $this->references->resolveActiveItem((int) $data['inventory_item_id']);

            [$sourceBalance, $destBalance] = $this->locker->lockPairForTransfer(
                (int) $sourceWarehouse->id,
                (int) $destWarehouse->id,
                (int) $item->id,
            );

            $sourceBefore = InventoryQuantity::format((string) $sourceBalance->on_hand);
            $sourceAfter = InventoryQuantity::applyOutbound($sourceBefore, $quantity);

            $destBefore = InventoryQuantity::format((string) $destBalance->on_hand);
            $destAfter = InventoryQuantity::applyInbound($destBefore, $quantity);

            $groupId = (string) Str::uuid();
            $occurredAt = now();
            $correlation = $this->correlationId->get();
            $reference = isset($data['reference']) && $data['reference'] !== ''
                ? (string) $data['reference']
                : null;

            $out = new InventoryMovement([
                'type' => MovementType::TransferOut,
                'warehouse_id' => $sourceWarehouse->id,
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'direction' => MovementDirection::Out,
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
                'transfer_group_id' => $groupId,
                'reason' => $reason,
                'reference' => $reference,
                'performed_by' => $actor->id,
                'occurred_at' => $occurredAt,
                'correlation_id' => $correlation,
            ]);
            $out->save();

            $in = new InventoryMovement([
                'type' => MovementType::TransferIn,
                'warehouse_id' => $destWarehouse->id,
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'direction' => MovementDirection::In,
                'balance_before' => $destBefore,
                'balance_after' => $destAfter,
                'transfer_group_id' => $groupId,
                'reason' => $reason,
                'reference' => $reference,
                'performed_by' => $actor->id,
                'occurred_at' => $occurredAt,
                'correlation_id' => $correlation,
            ]);
            $in->save();

            $sourceBalance->on_hand = $sourceAfter;
            $sourceBalance->save();

            $destBalance->on_hand = $destAfter;
            $destBalance->save();

            $this->security->record(AuthorizationSecurityEvent::STOCK_TRANSFERRED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'transfer_group_id' => $groupId,
                'transfer_out_movement_id' => $out->id,
                'transfer_out_movement_number' => $out->movement_number,
                'transfer_in_movement_id' => $in->id,
                'transfer_in_movement_number' => $in->movement_number,
                'source_warehouse_id' => $sourceWarehouse->id,
                'source_warehouse_number' => $sourceWarehouse->warehouse_number,
                'destination_warehouse_id' => $destWarehouse->id,
                'destination_warehouse_number' => $destWarehouse->warehouse_number,
                'inventory_item_id' => $item->id,
                'item_number' => $item->item_number,
                'quantity' => $quantity,
                'source_balance_before' => $sourceBefore,
                'source_balance_after' => $sourceAfter,
                'destination_balance_before' => $destBefore,
                'destination_balance_after' => $destAfter,
                'reason' => $reason,
            ], $request);

            $this->notifications->stockBelowMinimum($sourceWarehouse, $item, $sourceAfter);

            return [
                'transfer_out' => $out->load(['warehouse', 'item', 'performer']),
                'transfer_in' => $in->load(['warehouse', 'item', 'performer']),
                'transfer_group_id' => $groupId,
            ];
        });
    }
}
