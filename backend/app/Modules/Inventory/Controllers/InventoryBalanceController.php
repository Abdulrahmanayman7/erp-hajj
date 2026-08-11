<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\ListBalances;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Resources\InventoryBalanceResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryBalanceController
{
    use AuthorizesRequests;

    public function index(Request $request, ListBalances $action): JsonResponse
    {
        $this->authorize('viewAny', InventoryBalance::class);

        $paginator = $action->execute([
            'warehouse_id' => $request->query('warehouse_id'),
            'inventory_item_id' => $request->query('inventory_item_id'),
            'category_id' => $request->query('category_id'),
            'stock_state' => $request->query('stock_state'),
            'search' => $request->query('search'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: InventoryBalanceResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
