<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\RevokePurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Requests\GrantPurchaseApproverAuthorityRequest;
use App\Modules\SuppliersAndProcurement\Requests\RevokePurchaseApproverAuthorityRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePurchaseApproverAuthorityRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseApproverAuthorityResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseApproverAuthorityController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseApproverAuthority::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = PurchaseApproverAuthority::query()->orderByDesc('id');

        if ($request->boolean('active_only')) {
            $query->whereNull('revoked_at');
        }

        $paginator = $query->with(['user', 'organizationUnit', 'creator'])->paginate($perPage);

        return SupplierApiResponse::paginated(
            PurchaseApproverAuthorityResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(
        GrantPurchaseApproverAuthorityRequest $request,
        GrantPurchaseApproverAuthority $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseApproverAuthority::class);

        $authority = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseApproverAuthorityResource($authority))->resolve(),
            message: 'تم منح سلطة الاعتماد',
            status: 201,
        );
    }

    public function show(PurchaseApproverAuthority $purchase_approver_authority): JsonResponse
    {
        $this->authorize('view', $purchase_approver_authority);

        $purchase_approver_authority->load(['user', 'organizationUnit', 'creator']);

        return ApiResponse::success(
            data: (new PurchaseApproverAuthorityResource($purchase_approver_authority))->resolve(),
        );
    }

    public function update(
        UpdatePurchaseApproverAuthorityRequest $request,
        PurchaseApproverAuthority $purchase_approver_authority,
        UpdatePurchaseApproverAuthority $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approver_authority);

        $authority = $action->execute(
            $request->user(),
            $purchase_approver_authority,
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new PurchaseApproverAuthorityResource($authority))->resolve(),
            message: 'تم تحديث سلطة الاعتماد',
        );
    }

    public function revoke(
        RevokePurchaseApproverAuthorityRequest $request,
        PurchaseApproverAuthority $purchase_approver_authority,
        RevokePurchaseApproverAuthority $action,
    ): JsonResponse {
        $this->authorize('revoke', $purchase_approver_authority);

        $authority = $action->execute($request->user(), $purchase_approver_authority, $request);

        return ApiResponse::success(
            data: (new PurchaseApproverAuthorityResource($authority))->resolve(),
            message: 'تم إلغاء سلطة الاعتماد',
        );
    }
}
