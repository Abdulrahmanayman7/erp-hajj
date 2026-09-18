<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\ListMovements;
use App\Modules\Inventory\Actions\ShowMovement;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Resources\InventoryMovementResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController
{
    use AuthorizesRequests;

    public function index(Request $request, ListMovements $action): JsonResponse
    {
        $this->authorize('viewAny', InventoryBalance::class);

        $paginator = $action->execute([
            'warehouse_id' => $request->query('warehouse_id'),
            'inventory_item_id' => $request->query('inventory_item_id'),
            'type' => $request->query('type'),
            'performed_by' => $request->query('performed_by'),
            'transfer_group_id' => $request->query('transfer_group_id'),
            'occurred_from' => $request->query('occurred_from'),
            'occurred_to' => $request->query('occurred_to'),
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: InventoryMovementResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(InventoryMovement $movement, ShowMovement $action): JsonResponse
    {
        $this->authorize('viewAny', InventoryBalance::class);

        $movement = $action->execute($movement);

        return ApiResponse::success(
            data: (new InventoryMovementResource($movement))->resolve(),
        );
    }
}
