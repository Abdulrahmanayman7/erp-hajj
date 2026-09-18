<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivateInventoryItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, InventoryItem $item, Request $request): InventoryItem
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $item, $request, $tenant): InventoryItem {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->is_active = false;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::INVENTORY_ITEM_DEACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'inventory_item_id' => $locked->id,
                'item_number' => $locked->item_number,
            ], $request);

            return $locked->load(['category', 'createdBy']);
        });
    }
}
