<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\AddSupplierCoverage;
use App\Modules\SuppliersAndProcurement\Actions\DeleteSupplierCoverage;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use App\Modules\SuppliersAndProcurement\Requests\AddSupplierCoverageRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierCoverageResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCoverageController
{
    use AuthorizesRequests;

    public function index(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $coverages = $supplier->coverages()->with(['category', 'geoPlace'])->orderBy('id')->get();

        return ApiResponse::success(
            data: SupplierCoverageResource::collection($coverages)->resolve(),
        );
    }

    public function store(
        AddSupplierCoverageRequest $request,
        Supplier $supplier,
        AddSupplierCoverage $action,
    ): JsonResponse {
        $this->authorize('update', $supplier);

        $coverage = $action->execute($request->user(), $supplier, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierCoverageResource($coverage))->resolve(),
            message: 'تم إضافة التغطية الجغرافية',
            status: 201,
        );
    }

    public function destroy(
        Request $request,
        Supplier $supplier,
        SupplierCategoryCoverage $supplierCoverage,
        DeleteSupplierCoverage $action,
    ): JsonResponse {
        $this->authorize('update', $supplier);

        $action->execute($request->user(), $supplier, $supplierCoverage, $request);

        return ApiResponse::success(data: null, message: 'تم حذف التغطية الجغرافية');
    }
}
