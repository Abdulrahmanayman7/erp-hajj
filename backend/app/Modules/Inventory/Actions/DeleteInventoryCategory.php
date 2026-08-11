<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteInventoryCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, InventoryCategory $category, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $category, $request, $tenant): void {
            $locked = InventoryCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (InventoryItem::query()->where('category_id', $locked->id)->exists()) {
                throw InventoryDomainException::categoryInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::INVENTORY_CATEGORY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'inventory_category' => $snapshot,
            ], $request);
        });
    }
}
