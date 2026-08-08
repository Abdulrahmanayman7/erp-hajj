<?php

namespace App\Modules\Users\Controllers;

use App\Core\Shared\ApiResponse;
use App\Models\User;
use App\Modules\Users\Actions\CreateUser;
use App\Modules\Users\Actions\DisableUser;
use App\Modules\Users\Actions\EnableUser;
use App\Modules\Users\Actions\ListUsers;
use App\Modules\Users\Actions\SyncUserRoles;
use App\Modules\Users\Actions\UpdateUser;
use App\Modules\Users\Requests\AssignUserRolesRequest;
use App\Modules\Users\Requests\CreateUserRequest;
use App\Modules\Users\Requests\ToggleUserStatusRequest;
use App\Modules\Users\Requests\UpdateUserRequest;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    use AuthorizesRequests;

    public function index(Request $request, ListUsers $action): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'role_id' => $request->filled('role_id') ? (int) $request->query('role_id') : null,
            'sort' => $request->query('sort', 'name'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => (int) $request->query('per_page', 15),
        ]);

        return ApiResponse::success(
            data: UserResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateUserRequest $request, CreateUser $action): JsonResponse
    {
        $this->authorize('create', User::class);

        $result = $action->execute($request->user(), $request->validatedPayload(), $request);

        $data = (new UserResource($result['user']))->resolve();
        $data['password_provisioned'] = $result['password_provisioned'];

        return ApiResponse::success(
            data: $data,
            message: 'تم إنشاء المستخدم بنجاح',
            status: 201,
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        if ($user->tenant_id === null) {
            abort(404);
        }

        $user->load('roles:id,name,code');

        return ApiResponse::success(data: (new UserResource($user))->resolve());
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $action->execute($request->user(), $user, $request->validated(), $request);

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'تم تحديث المستخدم بنجاح',
        );
    }

    public function disable(ToggleUserStatusRequest $request, User $user, DisableUser $action): JsonResponse
    {
        $this->authorize('disable', $user);

        $user = $action->execute(
            $request->user(),
            $user,
            $request,
            $request->validated('reason'),
        );

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'تم تعطيل المستخدم',
        );
    }

    public function enable(ToggleUserStatusRequest $request, User $user, EnableUser $action): JsonResponse
    {
        $this->authorize('disable', $user);

        $user = $action->execute(
            $request->user(),
            $user,
            $request,
            $request->validated('reason'),
        );

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'تم تفعيل المستخدم',
        );
    }

    public function syncRoles(AssignUserRolesRequest $request, User $user, SyncUserRoles $action): JsonResponse
    {
        $this->authorize('assignRoles', $user);

        $user = $action->execute(
            $request->user(),
            $user,
            $request->validated('role_ids') ?? [],
            $request,
        );

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'تم تحديث أدوار المستخدم',
        );
    }
}
