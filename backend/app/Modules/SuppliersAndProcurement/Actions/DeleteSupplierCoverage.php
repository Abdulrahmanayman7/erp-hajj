<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteSupplierCoverage
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        Supplier $supplier,
        SupplierCategoryCoverage $coverage,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $supplier, $coverage, $request, $tenant): void {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $locked = SupplierCategoryCoverage::query()
                ->whereKey($coverage->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = ['id' => $locked->id, 'geo_place_id' => $locked->geo_place_id];
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_COVERAGE_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $supplier->id,
                'coverage' => $snapshot,
            ], $request);
        });
    }
}
