<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\ExternalDrivers\CreateExternalDriver;
use App\Modules\Transportation\Actions\ExternalDrivers\DeleteExternalDriver;
use App\Modules\Transportation\Actions\ExternalDrivers\SetExternalDriverActive;
use App\Modules\Transportation\Actions\ExternalDrivers\UpdateExternalDriver;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Requests\StoreExternalDriverRequest;
use App\Modules\Transportation\Resources\TransportExternalDriverResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-level registry (no season context).
 */
class TransportExternalDriverController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportExternalDriver::class);

        $query = TransportExternalDriver::query();
        PaginationMeta::applyRegistryFilters($query, $request->query('search'), $request->query('is_active'), ['full_name', 'phone']);

        $paginator = $query->orderBy('full_name')->orderBy('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportExternalDriverResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreExternalDriverRequest $request, CreateExternalDriver $action): JsonResponse
    {
        $this->authorize('create', TransportExternalDriver::class);

        $driver = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportExternalDriverResource($driver))->resolve(), message: 'تم تسجيل السائق الخارجي', status: 201);
    }

    public function show(TransportExternalDriver $externalDriver): JsonResponse
    {
        $this->authorize('view', $externalDriver);

        return ApiResponse::success(data: (new TransportExternalDriverResource($externalDriver))->resolve());
    }

    public function update(StoreExternalDriverRequest $request, TransportExternalDriver $externalDriver, UpdateExternalDriver $action): JsonResponse
    {
        $this->authorize('update', $externalDriver);

        $driver = $action->execute($request->user(), $externalDriver, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportExternalDriverResource($driver))->resolve(), message: 'تم تحديث السائق الخارجي');
    }

    public function destroy(Request $request, TransportExternalDriver $externalDriver, DeleteExternalDriver $action): JsonResponse
    {
        $this->authorize('delete', $externalDriver);

        $action->execute($request->user(), $externalDriver, $request);

        return ApiResponse::success(data: null, message: 'تم حذف السائق الخارجي');
    }

    public function activate(Request $request, TransportExternalDriver $externalDriver, SetExternalDriverActive $action): JsonResponse
    {
        return $this->setActive($request, $externalDriver, $action, true);
    }

    public function deactivate(Request $request, TransportExternalDriver $externalDriver, SetExternalDriverActive $action): JsonResponse
    {
        return $this->setActive($request, $externalDriver, $action, false);
    }

    private function setActive(Request $request, TransportExternalDriver $externalDriver, SetExternalDriverActive $action, bool $active): JsonResponse
    {
        $this->authorize('update', $externalDriver);

        $driver = $action->execute($request->user(), $externalDriver, $active, $request);

        return ApiResponse::success(data: (new TransportExternalDriverResource($driver))->resolve(), message: $active ? 'تم تفعيل السائق الخارجي' : 'تم إيقاف السائق الخارجي');
    }
}
