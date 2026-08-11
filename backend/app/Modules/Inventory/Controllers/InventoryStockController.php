<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Actions\ReturnStock;
use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Requests\AdjustStockRequest;
use App\Modules\Inventory\Requests\IssueStockRequest;
use App\Modules\Inventory\Requests\ReceiveStockRequest;
use App\Modules\Inventory\Requests\ReturnStockRequest;
use App\Modules\Inventory\Requests\TransferStockRequest;
use App\Modules\Inventory\Resources\InventoryMovementResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class InventoryStockController
{
    use AuthorizesRequests;

    public function receive(ReceiveStockRequest $request, ReceiveStock $action): JsonResponse
    {
        $this->authorize('receive', InventoryBalance::class);

        $movement = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryMovementResource($movement))->resolve(),
            message: 'تم استلام المخزون',
            status: 201,
        );
    }

    public function issue(IssueStockRequest $request, IssueStock $action): JsonResponse
    {
        $this->authorize('issue', InventoryBalance::class);

        $movement = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryMovementResource($movement))->resolve(),
            message: 'تم صرف المخزون',
            status: 201,
        );
    }

    public function returnStock(ReturnStockRequest $request, ReturnStock $action): JsonResponse
    {
        $this->authorize('returnStock', InventoryBalance::class);

        $movement = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryMovementResource($movement))->resolve(),
            message: 'تم إرجاع المخزون',
            status: 201,
        );
    }

    public function transfer(TransferStockRequest $request, TransferStock $action): JsonResponse
    {
        $this->authorize('transfer', InventoryBalance::class);

        $result = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: [
                'transfer_group_id' => $result['transfer_group_id'],
                'transfer_out' => (new InventoryMovementResource($result['transfer_out']))->resolve(),
                'transfer_in' => (new InventoryMovementResource($result['transfer_in']))->resolve(),
            ],
            message: 'تم تحويل المخزون',
            status: 201,
        );
    }

    public function adjust(AdjustStockRequest $request, AdjustStock $action): JsonResponse
    {
        $this->authorize('adjust', InventoryBalance::class);

        $movement = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryMovementResource($movement))->resolve(),
            message: 'تم تسوية المخزون',
            status: 201,
        );
    }
}
