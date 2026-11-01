<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteSupplierCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SupplierCategory $category, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $category, $request, $tenant): void {
            $locked = SupplierCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if ($locked->suppliers()->exists()
                || SupplierDocumentRequirementDef::query()->where('supplier_category_id', $locked->id)->exists()) {
                throw SupplierDomainException::categoryInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
                'code' => $locked->code,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CATEGORY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_category' => $snapshot,
            ], $request);
        });
    }
}
