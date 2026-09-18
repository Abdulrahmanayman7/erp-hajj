<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Support\Collection;

final class ListAssetCategories
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return Collection<int, AssetCategory>
     */
    public function execute(?bool $activeOnly = null): Collection
    {
        $this->tenantContext->require();

        $query = AssetCategory::query()->orderBy('name');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}
