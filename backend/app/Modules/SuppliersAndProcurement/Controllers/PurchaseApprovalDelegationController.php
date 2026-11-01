<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Actions\RevokePurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Requests\CreatePurchaseApprovalDelegationRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseApprovalDelegationResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseApprovalDelegationController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseApprovalDelegation::class);

        $actor = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = PurchaseApprovalDelegation::query()->orderByDesc('id');

        if (! $actor->hasPermission('purchase_requests.manage_delegations')) {
            $query->where(function ($q) use ($actor): void {
                $q->where('from_user_id', $actor->id)->orWhere('to_user_id', $actor->id);
            });
        }

        if ($request->boolean('active_only')) {
            $now = now();
            $query->whereNull('revoked_at')
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now);
        }

        $paginator = $query->with(['fromUser', 'toUser', 'authorizer'])->paginate($perPage);

        return SupplierApiResponse::paginated(
            PurchaseApprovalDelegationResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(
        CreatePurchaseApprovalDelegationRequest $request,
        CreatePurchaseApprovalDelegation $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseApprovalDelegation::class);

        $delegation = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseApprovalDelegationResource($delegation))->resolve(),
            message: 'تم إنشاء تفويض الاعتماد',
            status: 201,
        );
    }

    public function show(PurchaseApprovalDelegation $purchase_approval_delegation): JsonResponse
    {
        $this->authorize('view', $purchase_approval_delegation);

        $purchase_approval_delegation->load(['fromUser', 'toUser', 'authorizer']);

        return ApiResponse::success(
            data: (new PurchaseApprovalDelegationResource($purchase_approval_delegation))->resolve(),
        );
    }

    public function revoke(
        Request $request,
        PurchaseApprovalDelegation $purchase_approval_delegation,
        RevokePurchaseApprovalDelegation $action,
    ): JsonResponse {
        $this->authorize('revoke', $purchase_approval_delegation);

        $delegation = $action->execute($request->user(), $purchase_approval_delegation, $request);

        return ApiResponse::success(
            data: (new PurchaseApprovalDelegationResource($delegation))->resolve(),
            message: 'تم إلغاء تفويض الاعتماد',
        );
    }
}
