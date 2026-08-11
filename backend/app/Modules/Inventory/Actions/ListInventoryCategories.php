<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Support\Collection;

final class ListInventoryCategories
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return Collection<int, InventoryCategory>
     */
    public function execute(?bool $activeOnly = null): Collection
    {
        $this->tenantContext->require();

        $query = InventoryCategory::query()->orderBy('name');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}
