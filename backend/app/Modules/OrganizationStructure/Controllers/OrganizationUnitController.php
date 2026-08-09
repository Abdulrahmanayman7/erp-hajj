<?php

namespace App\Modules\OrganizationStructure\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\OrganizationStructure\Actions\ActivateOrganizationUnit;
use App\Modules\OrganizationStructure\Actions\CreateOrganizationUnit;
use App\Modules\OrganizationStructure\Actions\DeactivateOrganizationUnit;
use App\Modules\OrganizationStructure\Actions\DeleteOrganizationUnit;
use App\Modules\OrganizationStructure\Actions\ListOrganizationUnits;
use App\Modules\OrganizationStructure\Actions\MoveOrganizationUnit;
use App\Modules\OrganizationStructure\Actions\UpdateOrganizationUnit;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Requests\CreateOrganizationUnitRequest;
use App\Modules\OrganizationStructure\Requests\MoveOrganizationUnitRequest;
use App\Modules\OrganizationStructure\Requests\UpdateOrganizationUnitRequest;
use App\Modules\OrganizationStructure\Resources\OrganizationUnitResource;
use App\Modules\OrganizationStructure\Support\OrganizationHierarchy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUnitController
{
    use AuthorizesRequests;

    public function index(Request $request, ListOrganizationUnits $action): JsonResponse
    {
        $this->authorize('viewAny', OrganizationUnit::class);

        $result = $action->execute([
            'view' => $request->query('view', 'tree'),
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search'),
            'parent_id' => $request->query('parent_id'),
            'per_page' => $request->query('per_page'),
        ]);

        if ($result['mode'] === 'tree') {
            $data = array_map(function (array $node): array {
                $resource = new OrganizationUnitResource($node['unit']);
                $resource->withChildren = true;
                $resource->treeMeta = [
                    'children' => $node['children'],
                    'children_count' => $node['children_count'],
                    'depth' => $node['depth'],
                ];

                return $resource->resolve();
            }, $result['roots']);

            return ApiResponse::success(data: $data);
        }

        if ($result['mode'] === 'flat_all') {
            $depths = $result['depths'];
            $data = $result['units']->map(function (OrganizationUnit $unit) use ($depths) {
                $resource = new OrganizationUnitResource($unit);
                $resource->treeMeta = [
                    'children' => [],
                    'children_count' => 0,
                    'depth' => $depths[$unit->id] ?? 0,
                ];
                $unit->loadCount('children');
                $resource->treeMeta['children_count'] = $unit->children_count;

                return $resource->resolve();
            })->values()->all();

            return ApiResponse::success(data: $data, meta: [
                'total' => count($data),
            ]);
        }

        $paginator = $result['paginator'];
        $depths = $result['depths'];
        $data = collect($paginator->items())->map(function (OrganizationUnit $unit) use ($depths) {
            $resource = new OrganizationUnitResource($unit);
            $unit->loadCount('children');
            $resource->treeMeta = [
                'children' => [],
                'children_count' => $unit->children_count,
                'depth' => $depths[$unit->id] ?? 0,
            ];

            return $resource->resolve();
        })->all();

        return ApiResponse::success(
            data: $data,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateOrganizationUnitRequest $request, CreateOrganizationUnit $action): JsonResponse
    {
        $this->authorize('create', OrganizationUnit::class);

        $unit = $action->execute($request->user(), $request->validated(), $request);
        $resource = $this->toResource($unit);

        return ApiResponse::success(
            data: $resource->resolve(),
            message: 'تم إنشاء الوحدة التنظيمية بنجاح',
            status: 201,
        );
    }

    public function show(OrganizationUnit $organizationUnit): JsonResponse
    {
        $this->authorize('view', $organizationUnit);

        $organizationUnit->load('manager')->loadCount('children');
        $resource = $this->toResource($organizationUnit);

        return ApiResponse::success(data: $resource->resolve());
    }

    public function update(
        UpdateOrganizationUnitRequest $request,
        OrganizationUnit $organizationUnit,
        UpdateOrganizationUnit $action,
    ): JsonResponse {
        $this->authorize('update', $organizationUnit);

        $unit = $action->execute($request->user(), $organizationUnit, $request->validated(), $request);

        return ApiResponse::success(
            data: $this->toResource($unit)->resolve(),
            message: 'تم تحديث الوحدة التنظيمية بنجاح',
        );
    }

    public function move(
        MoveOrganizationUnitRequest $request,
        OrganizationUnit $organizationUnit,
        MoveOrganizationUnit $action,
    ): JsonResponse {
        $this->authorize('update', $organizationUnit);

        $parentId = $request->validated('parent_id');
        $unit = $action->execute(
            $request->user(),
            $organizationUnit,
            $parentId !== null ? (int) $parentId : null,
            $request,
        );

        return ApiResponse::success(
            data: $this->toResource($unit)->resolve(),
            message: 'تم نقل الوحدة التنظيمية بنجاح',
        );
    }

    public function activate(
        Request $request,
        OrganizationUnit $organizationUnit,
        ActivateOrganizationUnit $action,
    ): JsonResponse {
        $this->authorize('update', $organizationUnit);

        $unit = $action->execute($request->user(), $organizationUnit, $request);

        return ApiResponse::success(
            data: $this->toResource($unit)->resolve(),
            message: 'تم تفعيل الوحدة التنظيمية',
        );
    }

    public function deactivate(
        Request $request,
        OrganizationUnit $organizationUnit,
        DeactivateOrganizationUnit $action,
    ): JsonResponse {
        $this->authorize('update', $organizationUnit);

        $unit = $action->execute($request->user(), $organizationUnit, $request);

        return ApiResponse::success(
            data: $this->toResource($unit)->resolve(),
            message: 'تم تعطيل الوحدة التنظيمية',
        );
    }

    public function destroy(
        Request $request,
        OrganizationUnit $organizationUnit,
        DeleteOrganizationUnit $action,
    ): JsonResponse {
        $this->authorize('delete', $organizationUnit);

        $action->execute($request->user(), $organizationUnit, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الوحدة التنظيمية',
        );
    }

    private function toResource(OrganizationUnit $unit): OrganizationUnitResource
    {
        $resource = new OrganizationUnitResource($unit);
        $resource->treeMeta = [
            'children' => [],
            'children_count' => $unit->children_count ?? 0,
            'depth' => OrganizationHierarchy::computeDepth($unit),
        ];

        return $resource;
    }
}
