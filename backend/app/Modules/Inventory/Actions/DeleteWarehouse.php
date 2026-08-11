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
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteWarehouse
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentReferenceValidator $documents,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Warehouse $warehouse, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $warehouse, $request, $tenant): void {
            $locked = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            if (InventoryMovement::query()->where('warehouse_id', $locked->id)->exists()) {
                throw InventoryDomainException::warehouseInUse();
            }

            if (InventoryBalance::query()->where('warehouse_id', $locked->id)->exists()) {
                throw InventoryDomainException::warehouseInUse();
            }

            $this->documents->assertNoDocumentsLinked(DocumentLinkableType::Warehouse, (int) $locked->id);

            $snapshot = [
                'id' => $locked->id,
                'warehouse_number' => $locked->warehouse_number,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::WAREHOUSE_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'warehouse' => $snapshot,
            ], $request);
        });
    }
}
