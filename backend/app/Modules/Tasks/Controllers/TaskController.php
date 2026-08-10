<?php

namespace App\Modules\Tasks\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Tasks\Actions\AssignTask;
use App\Modules\Tasks\Actions\CancelTask;
use App\Modules\Tasks\Actions\CompleteTask;
use App\Modules\Tasks\Actions\CreateTask;
use App\Modules\Tasks\Actions\DeleteTask;
use App\Modules\Tasks\Actions\ListTasks;
use App\Modules\Tasks\Actions\StartTask;
use App\Modules\Tasks\Actions\UpdateTask;
use App\Modules\Tasks\Actions\UpdateTaskProgress;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Requests\AssignTaskRequest;
use App\Modules\Tasks\Requests\CancelTaskRequest;
use App\Modules\Tasks\Requests\CompleteTaskRequest;
use App\Modules\Tasks\Requests\CreateTaskRequest;
use App\Modules\Tasks\Requests\TaskCommentRequest;
use App\Modules\Tasks\Requests\UpdateTaskProgressRequest;
use App\Modules\Tasks\Requests\UpdateTaskRequest;
use App\Modules\Tasks\Resources\TaskResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController
{
    use AuthorizesRequests;

    public function index(Request $request, ListTasks $action): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'decision_id' => $request->query('decision_id'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'assigned_to_employee_id' => $request->query('assigned_to_employee_id'),
            'assigned_to_me' => $request->query('assigned_to_me'),
            'priority' => $request->query('priority'),
            'due_date_from' => $request->query('due_date_from'),
            'due_date_to' => $request->query('due_date_to'),
            'overdue' => $request->query('overdue'),
            'sort' => $request->query('sort'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ], $request->user());

        return ApiResponse::success(
            data: TaskResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateTaskRequest $request, CreateTask $action): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم إنشاء المهمة بنجاح',
            status: 201,
        );
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load([
            'decision',
            'organizationUnit',
            'assignee',
            'createdBy',
            'statusTransitions.performer',
            'assignmentHistory.performer',
            'assignmentHistory.fromEmployee',
            'assignmentHistory.toEmployee',
        ]);

        return ApiResponse::success(data: (new TaskResource($task))->resolve());
    }

    public function update(
        UpdateTaskRequest $request,
        Task $task,
        UpdateTask $action,
    ): JsonResponse {
        $this->authorize('update', $task);

        $task = $action->execute($request->user(), $task, $request->validated(), $request);

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم تحديث المهمة بنجاح',
        );
    }

    public function destroy(Request $request, Task $task, DeleteTask $action): JsonResponse
    {
        $this->authorize('delete', $task);

        $action->execute($request->user(), $task, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف المهمة',
        );
    }

    public function assign(
        AssignTaskRequest $request,
        Task $task,
        AssignTask $action,
    ): JsonResponse {
        $this->authorize('assign', $task);

        $task = $action->execute(
            $request->user(),
            $task,
            (int) $request->validated('assigned_to_employee_id'),
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم تعيين المهمة',
        );
    }

    public function start(
        TaskCommentRequest $request,
        Task $task,
        StartTask $action,
    ): JsonResponse {
        $this->authorize('start', $task);

        $task = $action->execute(
            $request->user(),
            $task,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم بدء المهمة',
        );
    }

    public function progress(
        UpdateTaskProgressRequest $request,
        Task $task,
        UpdateTaskProgress $action,
    ): JsonResponse {
        $this->authorize('updateProgress', $task);

        $task = $action->execute(
            $request->user(),
            $task,
            (int) $request->validated('progress_percent'),
            $request,
        );

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم تحديث التقدم',
        );
    }

    public function complete(
        CompleteTaskRequest $request,
        Task $task,
        CompleteTask $action,
    ): JsonResponse {
        $this->authorize('complete', $task);

        $task = $action->execute(
            $request->user(),
            $task,
            (string) $request->validated('completion_notes'),
            $request,
        );

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم إكمال المهمة',
        );
    }

    public function cancel(
        CancelTaskRequest $request,
        Task $task,
        CancelTask $action,
    ): JsonResponse {
        $this->authorize('cancel', $task);

        $task = $action->execute(
            $request->user(),
            $task,
            (string) $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new TaskResource($task))->resolve(),
            message: 'تم إلغاء المهمة',
        );
    }
}
