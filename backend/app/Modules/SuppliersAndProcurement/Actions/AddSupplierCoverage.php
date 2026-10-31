<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use App\Modules\SuppliersAndProcurement\Support\SupplierReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddSupplierCoverage
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{supplier_category_id: int, geo_place_id: int}  $data
     */
    public function execute(User $actor, Supplier $supplier, array $data, Request $request): SupplierCategoryCoverage
    {
        $tenant = $this->tenantContext->require();
        $categoryId = (int) $data['supplier_category_id'];
        $geoPlaceId = (int) $data['geo_place_id'];

        return DB::transaction(function () use ($actor, $supplier, $request, $tenant, $categoryId, $geoPlaceId): SupplierCategoryCoverage {
            $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $this->references->assertCategoryLinkedToSupplier($locked, $categoryId);
            $this->references->resolveActiveGeoPlace($geoPlaceId);

            $coverage = SupplierCategoryCoverage::query()->firstOrCreate(
                [
                    'supplier_id' => $locked->id,
                    'supplier_category_id' => $categoryId,
                    'geo_place_id' => $geoPlaceId,
                ],
            );

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_COVERAGE_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $locked->id,
                'coverage_id' => $coverage->id,
            ], $request);

            return $coverage->load(['category', 'geoPlace']);
        });
    }
}
