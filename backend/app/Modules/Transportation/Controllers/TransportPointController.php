<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\Points\CreateTransportPoint;
use App\Modules\Transportation\Actions\Points\DeleteTransportPoint;
use App\Modules\Transportation\Actions\Points\SetTransportPointActive;
use App\Modules\Transportation\Actions\Points\UpdateTransportPoint;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Requests\StoreTransportPointRequest;
use App\Modules\Transportation\Resources\TransportPointResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-level registry (no season context).
 */
class TransportPointController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportPoint::class);

        $query = TransportPoint::query();
        PaginationMeta::applyRegistryFilters($query, $request->query('search'), $request->query('is_active'), ['code', 'name']);
        if (is_string($type = $request->query('point_type')) && $type !== '') {
            $query->where('point_type', $type);
        }

        $paginator = $query->orderBy('code')->orderBy('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportPointResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreTransportPointRequest $request, CreateTransportPoint $action): JsonResponse
    {
        $this->authorize('create', TransportPoint::class);

        $point = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportPointResource($point))->resolve(), message: 'تم إنشاء نقطة النقل', status: 201);
    }

    public function show(TransportPoint $transportPoint): JsonResponse
    {
        $this->authorize('view', $transportPoint);

        return ApiResponse::success(data: (new TransportPointResource($transportPoint))->resolve());
    }

    public function update(StoreTransportPointRequest $request, TransportPoint $transportPoint, UpdateTransportPoint $action): JsonResponse
    {
        $this->authorize('update', $transportPoint);

        $point = $action->execute($request->user(), $transportPoint, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportPointResource($point))->resolve(), message: 'تم تحديث نقطة النقل');
    }

    public function destroy(Request $request, TransportPoint $transportPoint, DeleteTransportPoint $action): JsonResponse
    {
        $this->authorize('delete', $transportPoint);

        $action->execute($request->user(), $transportPoint, $request);

        return ApiResponse::success(data: null, message: 'تم حذف نقطة النقل');
    }

    public function activate(Request $request, TransportPoint $transportPoint, SetTransportPointActive $action): JsonResponse
    {
        return $this->setActive($request, $transportPoint, $action, true);
    }

    public function deactivate(Request $request, TransportPoint $transportPoint, SetTransportPointActive $action): JsonResponse
    {
        return $this->setActive($request, $transportPoint, $action, false);
    }

    private function setActive(Request $request, TransportPoint $transportPoint, SetTransportPointActive $action, bool $active): JsonResponse
    {
        $this->authorize('update', $transportPoint);

        $point = $action->execute($request->user(), $transportPoint, $active, $request);

        return ApiResponse::success(data: (new TransportPointResource($point))->resolve(), message: $active ? 'تم تفعيل نقطة النقل' : 'تم إيقاف نقطة النقل');
    }
}
