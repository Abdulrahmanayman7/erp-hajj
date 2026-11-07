<?php

namespace App\Modules\RisksAndComplaints\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\RisksAndComplaints\Actions\AddComplaintNote;
use App\Modules\RisksAndComplaints\Actions\AssignComplaint;
use App\Modules\RisksAndComplaints\Actions\CloseComplaint;
use App\Modules\RisksAndComplaints\Actions\CreateComplaint;
use App\Modules\RisksAndComplaints\Actions\DeleteComplaint;
use App\Modules\RisksAndComplaints\Actions\EscalateComplaint;
use App\Modules\RisksAndComplaints\Actions\ListComplaints;
use App\Modules\RisksAndComplaints\Actions\ListOperationalEntityTimeline;
use App\Modules\RisksAndComplaints\Actions\ReopenComplaint;
use App\Modules\RisksAndComplaints\Actions\ResolveComplaint;
use App\Modules\RisksAndComplaints\Actions\UpdateComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Requests\AddComplaintNoteRequest;
use App\Modules\RisksAndComplaints\Requests\AssignComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\CloseComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\CreateComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\EscalateComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\ReopenComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\ResolveComplaintRequest;
use App\Modules\RisksAndComplaints\Requests\UpdateComplaintRequest;
use App\Modules\RisksAndComplaints\Resources\OperationalComplaintResource;
use App\Modules\RisksAndComplaints\Resources\OperationalTimelineEntryResource;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SeasonContext $seasonContext,
    ) {}

    public function index(Request $request, ListComplaints $action): JsonResponse
    {
        $this->authorize('viewAny', OperationalComplaint::class);

        $paginator = $action->execute($this->seasonContext->require(), [
            'q' => $request->query('q'),
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'category_code' => $request->query('category_code'),
            'assignee_employee_id' => $request->query('assignee_employee_id'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: OperationalComplaintResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateComplaintRequest $request, CreateComplaint $action): JsonResponse
    {
        $this->authorize('create', OperationalComplaint::class);

        $complaint = $action->execute(
            $request->user(),
            $this->seasonContext->require(),
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم إنشاء الشكوى بنجاح',
            status: 201,
        );
    }

    public function show(OperationalComplaint $complaint): JsonResponse
    {
        $this->authorize('view', $complaint);

        $complaint->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);

        return ApiResponse::success(data: (new OperationalComplaintResource($complaint))->resolve());
    }

    public function update(
        UpdateComplaintRequest $request,
        OperationalComplaint $complaint,
        UpdateComplaint $action,
    ): JsonResponse {
        $this->authorize('update', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم تحديث الشكوى بنجاح',
        );
    }

    public function assign(
        AssignComplaintRequest $request,
        OperationalComplaint $complaint,
        AssignComplaint $action,
    ): JsonResponse {
        $this->authorize('assign', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم تعيين الشكوى',
        );
    }

    public function escalate(
        EscalateComplaintRequest $request,
        OperationalComplaint $complaint,
        EscalateComplaint $action,
    ): JsonResponse {
        $this->authorize('escalate', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم تصعيد الشكوى',
        );
    }

    public function resolve(
        ResolveComplaintRequest $request,
        OperationalComplaint $complaint,
        ResolveComplaint $action,
    ): JsonResponse {
        $this->authorize('resolve', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم حل الشكوى',
        );
    }

    public function close(
        CloseComplaintRequest $request,
        OperationalComplaint $complaint,
        CloseComplaint $action,
    ): JsonResponse {
        $this->authorize('close', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم إغلاق الشكوى',
        );
    }

    public function reopen(
        ReopenComplaintRequest $request,
        OperationalComplaint $complaint,
        ReopenComplaint $action,
    ): JsonResponse {
        $this->authorize('reopen', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تم إعادة فتح الشكوى',
        );
    }

    public function addNote(
        AddComplaintNoteRequest $request,
        OperationalComplaint $complaint,
        AddComplaintNote $action,
    ): JsonResponse {
        $this->authorize('addNote', $complaint);

        $complaint = $action->execute($request->user(), $complaint, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalComplaintResource($complaint))->resolve(),
            message: 'تمت إضافة الملاحظة',
            status: 201,
        );
    }

    public function destroy(Request $request, OperationalComplaint $complaint, DeleteComplaint $action): JsonResponse
    {
        $this->authorize('delete', $complaint);

        $action->execute($request->user(), $complaint, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الشكوى',
        );
    }

    public function timeline(
        Request $request,
        OperationalComplaint $complaint,
        ListOperationalEntityTimeline $action,
    ): JsonResponse {
        $this->authorize('view', $complaint);

        $entries = $action->execute(
            'operational_complaint',
            (int) $complaint->id,
            (int) $request->query('per_page', 50),
        );

        return ApiResponse::success(
            data: OperationalTimelineEntryResource::collection($entries)->resolve(),
        );
    }
}
