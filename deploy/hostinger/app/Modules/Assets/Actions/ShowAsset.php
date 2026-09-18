<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Assets\Models\Asset;

final class ShowAsset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(Asset $asset): Asset
    {
        $this->tenantContext->require();

        return $asset->load([
            'category',
            'warehouse',
            'organizationUnit',
            'currentCustody.employee',
            'createdBy',
            'transitions' => fn ($q) => $q->orderBy('id')->with('performedBy'),
            'custodies' => fn ($q) => $q->orderByDesc('assigned_at')->orderByDesc('id')->with('employee'),
        ]);
    }
}
