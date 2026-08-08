<?php

namespace App\Modules\Authorization\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Authorization\Actions\ActivateRole;
use App\Modules\Authorization\Actions\CreateRole;
use App\Modules\Authorization\Actions\DeactivateRole;
use App\Modules\Authorization\Actions\DeleteRole;
use App\Modules\Authorization\Actions\ListRoles;
use App\Modules\Authorization\Actions\SyncRolePermissions;
use App\Modules\Authorization\Actions\UpdateRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Requests\CreateRoleRequest;
use App\Modules\Authorization\Requests\SyncRolePermissionsRequest;
use App\Modules\Authorization\Requests\UpdateRoleRequest;
use App\Modules\Authorization\Resources\RoleResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController
{
    use AuthorizesRequests;

    public function index(Request $request, ListRoles $action): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'is_active' => $request->has('is_active') ? filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'is_system' => $request->has('is_system') ? filter_var($request->query('is_system'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'sort' => $request->query('sort', 'name'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => (int) $request->query('per_page', 15),
        ]);

        return ApiResponse::success(
            data: RoleResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateRoleRequest $request, CreateRole $action): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new RoleResource($role))->resolve(),
            message: 'تم إنشاء الدور بنجاح',
            status: 201,
        );
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->load('permissions')->loadCount(['users', 'permissions']);
        $resource = new RoleResource($role);
        $resource->withPermissions = true;

        return ApiResponse::success(data: $resource->resolve());
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $action): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $action->execute($request->user(), $role, $request->validated(), $request);

        return ApiResponse::success(
            data: (new RoleResource($role))->resolve(),
            message: 'تم تحديث الدور بنجاح',
        );
    }

    public function deactivate(Request $request, Role $role, DeactivateRole $action): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $action->execute($request->user(), $role, $request);

        return ApiResponse::success(
            data: (new RoleResource($role))->resolve(),
            message: 'تم تعطيل الدور',
        );
    }

    public function activate(Request $request, Role $role, ActivateRole $action): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $action->execute($request->user(), $role, $request);

        return ApiResponse::success(
            data: (new RoleResource($role))->resolve(),
            message: 'تم تفعيل الدور',
        );
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role, SyncRolePermissions $action): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $role = $action->execute($request->user(), $role, $request->validated(), $request);
        $resource = new RoleResource($role);
        $resource->withPermissions = true;

        return ApiResponse::success(
            data: $resource->resolve(),
            message: 'تم تحديث صلاحيات الدور',
        );
    }

    public function destroy(Request $request, Role $role, DeleteRole $action): JsonResponse
    {
        $this->authorize('delete', $role);

        $action->execute($request->user(), $role, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الدور',
        );
    }
}
