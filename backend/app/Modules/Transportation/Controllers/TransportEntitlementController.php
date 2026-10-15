<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\Transportation\Actions\Entitlements\CreateEntitlement;
use App\Modules\Transportation\Actions\Entitlements\DeleteEntitlement;
use App\Modules\Transportation\Actions\Entitlements\UpdateEntitlement;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Requests\StoreEntitlementRequest;
use App\Modules\Transportation\Resources\TransportEntitlementResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class TransportEntitlementController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TransportEntitlement::class);

        $entitlements = TransportEntitlement::query()
            ->where('season_id', $this->context->require()->id)
            ->with('program')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(data: TransportEntitlementResource::collection($entitlements)->resolve());
    }

    public function store(StoreEntitlementRequest $request, CreateEntitlement $action): JsonResponse
    {
        $this->authorize('create', TransportEntitlement::class);

        $entitlement = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportEntitlementResource($entitlement->load('program')))->resolve(), message: 'تم إنشاء استحقاق النقل', status: 201);
    }

    public function show(TransportEntitlement $entitlement): JsonResponse
    {
        $this->authorize('view', $entitlement);

        return ApiResponse::success(data: (new TransportEntitlementResource($entitlement->load('program')))->resolve());
    }

    public function update(StoreEntitlementRequest $request, TransportEntitlement $entitlement, UpdateEntitlement $action): JsonResponse
    {
        $this->authorize('update', $entitlement);

        $updated = $action->execute($request->user(), $entitlement, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportEntitlementResource($updated->load('program')))->resolve(), message: 'تم تحديث استحقاق النقل');
    }

    public function destroy(Request $request, TransportEntitlement $entitlement, DeleteEntitlement $action): JsonResponse
    {
        $this->authorize('delete', $entitlement);

        $action->execute($request->user(), $entitlement, $request);

        return ApiResponse::success(data: null, message: 'تم حذف استحقاق النقل');
    }
}
