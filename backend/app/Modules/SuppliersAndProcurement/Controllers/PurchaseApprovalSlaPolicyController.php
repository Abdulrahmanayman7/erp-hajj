<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\DeletePurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Requests\UpsertPurchaseApprovalSlaPolicyRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseApprovalSlaPolicyResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseApprovalSlaPolicyController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseApprovalSlaPolicy::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = PurchaseApprovalSlaPolicy::query()
            ->orderBy('priority')
            ->orderBy('level_order')
            ->paginate($perPage);

        return SupplierApiResponse::paginated(
            PurchaseApprovalSlaPolicyResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function upsert(
        UpsertPurchaseApprovalSlaPolicyRequest $request,
        UpsertPurchaseApprovalSlaPolicy $action,
    ): JsonResponse {
        $this->authorize('upsert', PurchaseApprovalSlaPolicy::class);

        $policy = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseApprovalSlaPolicyResource($policy))->resolve(),
            message: 'تم حفظ سياسة SLA',
        );
    }

    public function destroy(
        Request $request,
        PurchaseApprovalSlaPolicy $purchase_approval_sla_policy,
        DeletePurchaseApprovalSlaPolicy $action,
    ): JsonResponse {
        $this->authorize('delete', $purchase_approval_sla_policy);

        $action->execute($request->user(), $purchase_approval_sla_policy, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف سياسة SLA',
        );
    }
}
