<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteInventoryItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentReferenceValidator $documents,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, InventoryItem $item, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $item, $request, $tenant): void {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if (InventoryMovement::query()->where('inventory_item_id', $locked->id)->exists()) {
                throw InventoryDomainException::itemInUse();
            }

            if (InventoryBalance::query()->where('inventory_item_id', $locked->id)->exists()) {
                throw InventoryDomainException::itemInUse();
            }

            $this->documents->assertNoDocumentsLinked(DocumentLinkableType::InventoryItem, (int) $locked->id);

            $snapshot = [
                'id' => $locked->id,
                'item_number' => $locked->item_number,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::INVENTORY_ITEM_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'inventory_item' => $snapshot,
            ], $request);
        });
    }
}
