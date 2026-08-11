<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Assets\Models\AssetCustody;

final class ShowCustody
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(AssetCustody $custody): AssetCustody
    {
        $this->tenantContext->require();

        return $custody->load([
            'asset.category',
            'employee',
            'assignedBy',
            'returnedBy',
        ]);
    }
}
