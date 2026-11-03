<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CancelPurchaseOrder;
use App\Modules\SuppliersAndProcurement\Actions\ClosePurchaseOrder;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseOrder;
use App\Modules\SuppliersAndProcurement\Actions\IssuePurchaseOrder;
use App\Modules\SuppliersAndProcurement\Actions\ListPurchaseOrders;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Requests\CancelPurchaseOrderRequest;
use App\Modules\SuppliersAndProcurement\Requests\ClosePurchaseOrderRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreatePurchaseOrderRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePurchaseOrderRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseOrderResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController
{
    use AuthorizesRequests;

    public function index(Request $request, ListPurchaseOrders $action): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $paginator = $action->execute($request->user(), [
            'status' => $request->query('status'),
            'purchase_request_id' => $request->query('purchase_request_id'),
            'rfq_id' => $request->query('rfq_id'),
            'supplier_id' => $request->query('supplier_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            PurchaseOrderResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreatePurchaseOrderRequest $request, CreatePurchaseOrder $action): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
            status: 201,
        );
    }

    public function show(PurchaseOrder $po): JsonResponse
    {
        $this->authorize('view', $po);

        $po->load([
            'lines',
            'supplier',
            'rfq',
            'purchaseRequest',
            'winningOffer',
            'contract',
            'creator',
            'issuer',
            'canceller',
            'closer',
        ]);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $po,
        UpdatePurchaseOrder $action,
    ): JsonResponse {
        $this->authorize('update', $po);

        $po = $action->execute($request->user(), $po, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
        );
    }

    public function issue(Request $request, PurchaseOrder $po, IssuePurchaseOrder $action): JsonResponse
    {
        $this->authorize('issue', $po);

        $po = $action->execute($request->user(), $po, $request);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
        );
    }

    public function cancel(
        CancelPurchaseOrderRequest $request,
        PurchaseOrder $po,
        CancelPurchaseOrder $action,
    ): JsonResponse {
        $this->authorize('cancel', $po);

        $po = $action->execute($request->user(), $po, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
        );
    }

    public function close(
        ClosePurchaseOrderRequest $request,
        PurchaseOrder $po,
        ClosePurchaseOrder $action,
    ): JsonResponse {
        $this->authorize('close', $po);

        $po = $action->execute($request->user(), $po, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseOrderResource($po))->resolve(),
        );
    }
}
