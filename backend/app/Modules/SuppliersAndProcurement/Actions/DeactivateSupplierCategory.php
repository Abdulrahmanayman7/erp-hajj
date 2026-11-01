<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivateSupplierCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SupplierCategory $category, Request $request): SupplierCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $request, $tenant): SupplierCategory {
            $locked = SupplierCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $locked->is_active = false;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CATEGORY_DEACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_category_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }
}
