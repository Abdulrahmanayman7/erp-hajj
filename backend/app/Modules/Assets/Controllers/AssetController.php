<?php

namespace App\Modules\Assets\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Assets\Actions\AssignCustody;
use App\Modules\Assets\Actions\CreateAsset;
use App\Modules\Assets\Actions\DeclareAssetLost;
use App\Modules\Assets\Actions\DeleteAsset;
use App\Modules\Assets\Actions\ListAssets;
use App\Modules\Assets\Actions\RestoreAsset;
use App\Modules\Assets\Actions\RetireAsset;
use App\Modules\Assets\Actions\ReturnCustody;
use App\Modules\Assets\Actions\SendAssetToMaintenance;
use App\Modules\Assets\Actions\ShowAsset;
use App\Modules\Assets\Actions\UpdateAsset;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Requests\AssignCustodyRequest;
use App\Modules\Assets\Requests\DeclareLostRequest;
use App\Modules\Assets\Requests\RetireAssetRequest;
use App\Modules\Assets\Requests\ReturnCustodyRequest;
use App\Modules\Assets\Requests\StoreAssetRequest;
use App\Modules\Assets\Requests\UpdateAssetRequest;
use App\Modules\Assets\Resources\AssetResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController
{
    use AuthorizesRequests;

    public function index(Request $request, ListAssets $action): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
            'warehouse_id' => $request->query('warehouse_id'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'employee_id' => $request->query('employee_id'),
            'serial_number' => $request->query('serial_number'),
            'acquisition_from' => $request->query('acquisition_from'),
            'acquisition_to' => $request->query('acquisition_to'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: AssetResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreAssetRequest $request, CreateAsset $action): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $asset = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم تسجيل الأصل بنجاح',
            status: 201,
        );
    }

    public function show(Asset $asset, ShowAsset $action): JsonResponse
    {
        $this->authorize('view', $asset);

        $asset = $action->execute($asset);

        return ApiResponse::success(data: (new AssetResource($asset))->resolve());
    }

    public function update(UpdateAssetRequest $request, Asset $asset, UpdateAsset $action): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $action->execute($request->user(), $asset, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم تحديث الأصل بنجاح',
        );
    }

    public function destroy(Request $request, Asset $asset, DeleteAsset $action): JsonResponse
    {
        $this->authorize('delete', $asset);

        $action->execute($request->user(), $asset, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الأصل',
        );
    }

    public function maintenance(Request $request, Asset $asset, SendAssetToMaintenance $action): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $action->execute($request->user(), $asset, $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم إرسال الأصل للصيانة',
        );
    }

    public function restore(Request $request, Asset $asset, RestoreAsset $action): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $action->execute($request->user(), $asset, $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم استعادة الأصل',
        );
    }

    public function retire(RetireAssetRequest $request, Asset $asset, RetireAsset $action): JsonResponse
    {
        $this->authorize('retire', $asset);

        $asset = $action->execute($request->user(), $asset, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم استبعاد الأصل',
        );
    }

    public function declareLost(DeclareLostRequest $request, Asset $asset, DeclareAssetLost $action): JsonResponse
    {
        $this->authorize('retire', $asset);

        $asset = $action->execute($request->user(), $asset, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم تسجيل الأصل كمفقود',
        );
    }

    public function assign(AssignCustodyRequest $request, Asset $asset, AssignCustody $action): JsonResponse
    {
        $this->authorize('assign', $asset);

        $asset = $action->execute($request->user(), $asset, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم تسليم العهدة',
        );
    }

    public function returnCustody(ReturnCustodyRequest $request, Asset $asset, ReturnCustody $action): JsonResponse
    {
        $this->authorize('returnCustody', $asset);

        $asset = $action->execute($request->user(), $asset, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetResource($asset))->resolve(),
            message: 'تم استلام العهدة',
        );
    }
}
