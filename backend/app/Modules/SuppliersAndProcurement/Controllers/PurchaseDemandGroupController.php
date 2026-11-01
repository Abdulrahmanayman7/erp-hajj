<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\LinkPurchaseRequestToDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\TransitionPurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Actions\UnlinkPurchaseRequestFromDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Requests\CreatePurchaseDemandGroupRequest;
use App\Modules\SuppliersAndProcurement\Requests\LinkPurchaseDemandGroupItemRequest;
use App\Modules\SuppliersAndProcurement\Requests\TransitionPurchaseDemandGroupRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePurchaseDemandGroupRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseDemandGroupResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseDemandGroupController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseDemandGroup::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = PurchaseDemandGroup::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $paginator = $query->with(['creator', 'season', 'beneficiaryUnit', 'activeMemberships.purchaseRequest'])
            ->paginate($perPage);

        return SupplierApiResponse::paginated(
            PurchaseDemandGroupResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(
        CreatePurchaseDemandGroupRequest $request,
        CreatePurchaseDemandGroup $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseDemandGroup::class);

        $group = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseDemandGroupResource($group))->resolve(),
            message: 'تم إنشاء مجموعة تخطيط الطلبات',
            status: 201,
        );
    }

    public function show(PurchaseDemandGroup $purchase_demand_group): JsonResponse
    {
        $this->authorize('view', $purchase_demand_group);

        $purchase_demand_group->load([
            'creator',
            'season',
            'beneficiaryUnit',
            'activeMemberships.purchaseRequest',
            'items',
        ]);

        return ApiResponse::success(
            data: (new PurchaseDemandGroupResource($purchase_demand_group))->resolve(),
        );
    }

    public function update(
        UpdatePurchaseDemandGroupRequest $request,
        PurchaseDemandGroup $purchase_demand_group,
        UpdatePurchaseDemandGroup $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_demand_group);

        $group = $action->execute($request->user(), $purchase_demand_group, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseDemandGroupResource($group))->resolve(),
            message: 'تم تحديث مجموعة تخطيط الطلبات',
        );
    }

    public function transition(
        TransitionPurchaseDemandGroupRequest $request,
        PurchaseDemandGroup $purchase_demand_group,
        TransitionPurchaseDemandGroupStatus $action,
    ): JsonResponse {
        $this->authorize('transition', $purchase_demand_group);

        $group = $action->execute($request->user(), $purchase_demand_group, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseDemandGroupResource($group))->resolve(),
            message: 'تم تحديث حالة مجموعة التخطيط',
        );
    }

    public function linkItem(
        LinkPurchaseDemandGroupItemRequest $request,
        PurchaseDemandGroup $purchase_demand_group,
        LinkPurchaseRequestToDemandGroup $action,
    ): JsonResponse {
        $this->authorize('link', $purchase_demand_group);

        $result = $action->execute($request->user(), $purchase_demand_group, $request->validated(), $request);

        return SupplierApiResponse::successWithWarnings(
            data: (new PurchaseDemandGroupResource($result['group']))->resolve(),
            warnings: $result['warnings'],
            message: 'تم ربط طلب الشراء بمجموعة التخطيط',
            status: 201,
        );
    }

    public function unlinkItem(
        Request $request,
        PurchaseDemandGroup $purchase_demand_group,
        int $purchaseRequestId,
        UnlinkPurchaseRequestFromDemandGroup $action,
    ): JsonResponse {
        $this->authorize('unlink', $purchase_demand_group);

        $group = $action->execute(
            $request->user(),
            $purchase_demand_group,
            $purchaseRequestId,
            $request,
        );

        return ApiResponse::success(
            data: (new PurchaseDemandGroupResource($group))->resolve(),
            message: 'تم فك ربط طلب الشراء من مجموعة التخطيط',
        );
    }
}
