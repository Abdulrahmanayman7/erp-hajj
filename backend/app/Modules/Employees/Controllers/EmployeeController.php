<?php

namespace App\Modules\Employees\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Employees\Actions\ActivateEmployee;
use App\Modules\Employees\Actions\AssignEmployeeSupervisor;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\DeactivateEmployee;
use App\Modules\Employees\Actions\LinkEmployeeUser;
use App\Modules\Employees\Actions\ListEmployees;
use App\Modules\Employees\Actions\UpdateEmployee;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Requests\AssignEmployeeSupervisorRequest;
use App\Modules\Employees\Requests\CreateEmployeeRequest;
use App\Modules\Employees\Requests\LinkEmployeeUserRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Resources\EmployeeResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController
{
    use AuthorizesRequests;

    public function index(Request $request, ListEmployees $action): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status', 'all'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'supervisor_id' => $request->query('supervisor_id'),
            'position_id' => $request->query('position_id'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: EmployeeResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateEmployeeRequest $request, CreateEmployee $action): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: 'تم إنشاء الموظف بنجاح',
            status: 201,
        );
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $employee->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);

        return ApiResponse::success(data: (new EmployeeResource($employee))->resolve());
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        UpdateEmployee $action,
    ): JsonResponse {
        $this->authorize('update', $employee);

        $employee = $action->execute($request->user(), $employee, $request->validated(), $request);

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: 'تم تحديث الموظف بنجاح',
        );
    }

    public function activate(Request $request, Employee $employee, ActivateEmployee $action): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $action->execute($request->user(), $employee, $request);

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: 'تم تفعيل الموظف',
        );
    }

    public function deactivate(Request $request, Employee $employee, DeactivateEmployee $action): JsonResponse
    {
        $this->authorize('deactivate', $employee);

        $employee = $action->execute($request->user(), $employee, $request);

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: 'تم تعطيل الموظف',
        );
    }

    public function assignSupervisor(
        AssignEmployeeSupervisorRequest $request,
        Employee $employee,
        AssignEmployeeSupervisor $action,
    ): JsonResponse {
        $this->authorize('assignSupervisor', $employee);

        $supervisorId = $request->validated('supervisor_id');
        $employee = $action->execute(
            $request->user(),
            $employee,
            $supervisorId !== null ? (int) $supervisorId : null,
            $request,
        );

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: 'تم تحديث المشرف المباشر',
        );
    }

    public function linkUser(
        LinkEmployeeUserRequest $request,
        Employee $employee,
        LinkEmployeeUser $action,
    ): JsonResponse {
        $this->authorize('update', $employee);

        $userId = $request->validated('user_id');
        $employee = $action->execute(
            $request->user(),
            $employee,
            $userId !== null ? (int) $userId : null,
            $request,
        );

        return ApiResponse::success(
            data: (new EmployeeResource($employee))->resolve(),
            message: $userId === null ? 'تم إلغاء ربط حساب المستخدم' : 'تم ربط حساب المستخدم',
        );
    }
}
