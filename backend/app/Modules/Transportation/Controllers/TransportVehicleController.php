<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\Vehicles\CreateTransportVehicle;
use App\Modules\Transportation\Actions\Vehicles\DeleteTransportVehicle;
use App\Modules\Transportation\Actions\Vehicles\SetTransportVehicleActive;
use App\Modules\Transportation\Actions\Vehicles\UpdateTransportVehicle;
use App\Modules\Transportation\Actions\Vehicles\UpdateTransportVehicleLinks;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Requests\StoreTransportVehicleRequest;
use App\Modules\Transportation\Requests\UpdateVehicleLinksRequest;
use App\Modules\Transportation\Resources\TransportVehicleResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-level registry (no season context).
 */
class TransportVehicleController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportVehicle::class);

        $query = TransportVehicle::query();
        PaginationMeta::applyRegistryFilters($query, $request->query('search'), $request->query('is_active'), ['vehicle_number', 'plate_number']);
        if (is_string($class = $request->query('vehicle_class')) && $class !== '') {
            $query->where('vehicle_class', $class);
        }

        $paginator = $query->orderBy('vehicle_number')->orderBy('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportVehicleResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreTransportVehicleRequest $request, CreateTransportVehicle $action): JsonResponse
    {
        $this->authorize('create', TransportVehicle::class);

        $vehicle = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportVehicleResource($vehicle))->resolve(), message: 'تم تسجيل المركبة', status: 201);
    }

    public function show(TransportVehicle $transportVehicle): JsonResponse
    {
        $this->authorize('view', $transportVehicle);

        return ApiResponse::success(data: (new TransportVehicleResource($transportVehicle->load(['asset', 'contract'])))->resolve());
    }

    public function update(StoreTransportVehicleRequest $request, TransportVehicle $transportVehicle, UpdateTransportVehicle $action): JsonResponse
    {
        $this->authorize('update', $transportVehicle);

        $vehicle = $action->execute($request->user(), $transportVehicle, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportVehicleResource($vehicle))->resolve(), message: 'تم تحديث المركبة');
    }

    public function destroy(Request $request, TransportVehicle $transportVehicle, DeleteTransportVehicle $action): JsonResponse
    {
        $this->authorize('delete', $transportVehicle);

        $action->execute($request->user(), $transportVehicle, $request);

        return ApiResponse::success(data: null, message: 'تم حذف المركبة');
    }

    public function activate(Request $request, TransportVehicle $transportVehicle, SetTransportVehicleActive $action): JsonResponse
    {
        return $this->setActive($request, $transportVehicle, $action, true);
    }

    public function deactivate(Request $request, TransportVehicle $transportVehicle, SetTransportVehicleActive $action): JsonResponse
    {
        return $this->setActive($request, $transportVehicle, $action, false);
    }

    public function links(UpdateVehicleLinksRequest $request, TransportVehicle $transportVehicle, UpdateTransportVehicleLinks $action): JsonResponse
    {
        $this->authorize('update', $transportVehicle);

        $assetId = $request->validated('asset_id');
        $contractId = $request->validated('contract_id');
        $vehicle = $action->execute(
            $request->user(),
            $transportVehicle,
            $assetId !== null ? (int) $assetId : null,
            $contractId !== null ? (int) $contractId : null,
            $request,
        );

        return ApiResponse::success(data: (new TransportVehicleResource($vehicle->load(['asset', 'contract'])))->resolve(), message: 'تم تحديث روابط المركبة');
    }

    private function setActive(Request $request, TransportVehicle $transportVehicle, SetTransportVehicleActive $action, bool $active): JsonResponse
    {
        $this->authorize('update', $transportVehicle);

        $vehicle = $action->execute($request->user(), $transportVehicle, $active, $request);

        return ApiResponse::success(data: (new TransportVehicleResource($vehicle))->resolve(), message: $active ? 'تم تفعيل المركبة' : 'تم إيقاف المركبة');
    }
}
