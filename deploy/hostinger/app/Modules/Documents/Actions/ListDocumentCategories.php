<?php

namespace App\Modules\Documents\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Documents\Models\DocumentCategory;
use Illuminate\Support\Collection;

final class ListDocumentCategories
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return Collection<int, DocumentCategory>
     */
    public function execute(?bool $activeOnly = null): Collection
    {
        $this->tenantContext->require();

        $query = DocumentCategory::query()->orderBy('name');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}
