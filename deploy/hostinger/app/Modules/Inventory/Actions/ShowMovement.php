<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\InventoryMovement;

final class ShowMovement
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(InventoryMovement $movement): InventoryMovement
    {
        $this->tenantContext->require();

        return $movement->load(['warehouse', 'item.category', 'performer']);
    }
}
