<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplier;
use App\Modules\SuppliersAndProcurement\Actions\DeleteSupplier;
use App\Modules\SuppliersAndProcurement\Actions\ListSuppliers;
use App\Modules\SuppliersAndProcurement\Actions\TransitionSupplier;
use App\Modules\SuppliersAndProcurement\Actions\UpdateSupplier;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierRequest;
use App\Modules\SuppliersAndProcurement\Requests\SupplierTransitionRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateSupplierRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController
{
    use AuthorizesRequests;

    public function index(Request $request, ListSuppliers $action): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $paginator = $action->execute([
            'status' => $request->query('status'),
            'supplier_type' => $request->query('supplier_type'),
            'q' => $request->query('q'),
            'category_id' => $request->query('category_id'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return SupplierApiResponse::paginated(
            SupplierResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateSupplierRequest $request, CreateSupplier $action): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $result = $action->execute($request->user(), $request->validated(), $request);

        return SupplierApiResponse::createdWithWarnings(
            (new SupplierResource($result['supplier']))->resolve(),
            $result['warnings'],
            'تم إنشاء المورد بنجاح',
        );
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $supplier->load([
            'categories',
            'createdBy',
            'statusTransitions.actor',
            'contacts',
            'coverages.category',
            'coverages.geoPlace',
        ]);

        return ApiResponse::success(data: (new SupplierResource($supplier))->resolve());
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier,
        UpdateSupplier $action,
    ): JsonResponse {
        $this->authorize('update', $supplier);

        $result = $action->execute($request->user(), $supplier, $request->validated(), $request);

        return SupplierApiResponse::successWithWarnings(
            (new SupplierResource($result['supplier']))->resolve(),
            $result['warnings'],
            'تم تحديث المورد بنجاح',
        );
    }

    public function destroy(Request $request, Supplier $supplier, DeleteSupplier $action): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $action->execute($request->user(), $supplier, $request);

        return ApiResponse::success(data: null, message: 'تم حذف المورد');
    }

    public function transition(
        SupplierTransitionRequest $request,
        Supplier $supplier,
        TransitionSupplier $action,
    ): JsonResponse {
        $this->authorize('transition', $supplier);

        $to = SupplierStatus::from($request->validated('to'));
        $supplier = $action->execute(
            $request->user(),
            $supplier,
            $to,
            $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierResource($supplier))->resolve(),
            message: 'تم تحديث حالة المورد',
        );
    }
}
