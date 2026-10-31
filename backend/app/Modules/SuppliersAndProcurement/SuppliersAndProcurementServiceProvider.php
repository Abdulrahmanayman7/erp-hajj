<?php

namespace App\Modules\SuppliersAndProcurement;

use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use App\Modules\SuppliersAndProcurement\Models\SupplierContact;
use App\Modules\SuppliersAndProcurement\Policies\GeoPlacePolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierCategoryPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SuppliersAndProcurementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(SupplierCategory::class, SupplierCategoryPolicy::class);
        Gate::policy(GeoPlace::class, GeoPlacePolicy::class);

        Route::bind('supplier_contact', function (string $value, $route): SupplierContact {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $supplierId = $this->routeSupplierId($route->parameter('supplier'));

            return SupplierContact::query()
                ->whereKey($value)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();
        });

        Route::bind('supplier_coverage', function (string $value, $route): SupplierCategoryCoverage {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $supplierId = $this->routeSupplierId($route->parameter('supplier'));

            return SupplierCategoryCoverage::query()
                ->whereKey($value)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();
        });
    }

    private function routeSupplierId(mixed $supplier): int
    {
        if ($supplier instanceof Supplier) {
            return (int) $supplier->id;
        }

        return (int) $supplier;
    }
}
