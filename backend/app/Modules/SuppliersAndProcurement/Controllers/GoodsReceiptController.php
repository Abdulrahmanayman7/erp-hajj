<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateGoodsReceipt;
use App\Modules\SuppliersAndProcurement\Actions\ListGoodsReceipts;
use App\Modules\SuppliersAndProcurement\Actions\PostGoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Requests\CreateGoodsReceiptRequest;
use App\Modules\SuppliersAndProcurement\Resources\GoodsReceiptResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController
{
    use AuthorizesRequests;

    public function index(Request $request, ListGoodsReceipts $action): JsonResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $paginator = $action->execute($request->user(), [
            'status' => $request->query('status'),
            'purchase_order_id' => $request->query('purchase_order_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            GoodsReceiptResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateGoodsReceiptRequest $request, CreateGoodsReceipt $action): JsonResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        $grn = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new GoodsReceiptResource($grn))->resolve(),
            status: 201,
        );
    }

    public function show(GoodsReceipt $grn): JsonResponse
    {
        $this->authorize('view', $grn);

        $grn->load([
            'lines.purchaseOrderLine',
            'lines.inventoryMovement',
            'purchaseOrder.lines',
            'warehouse',
            'creator',
            'poster',
        ]);

        return ApiResponse::success(
            data: (new GoodsReceiptResource($grn))->resolve(),
        );
    }

    public function post(Request $request, GoodsReceipt $grn, PostGoodsReceipt $action): JsonResponse
    {
        $this->authorize('post', $grn);

        $grn = $action->execute($request->user(), $grn, $request);

        return ApiResponse::success(
            data: (new GoodsReceiptResource($grn))->resolve(),
        );
    }
}
