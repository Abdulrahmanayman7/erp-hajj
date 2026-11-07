<?php

namespace App\Modules\RisksAndComplaints\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\RisksAndComplaints\Actions\ApproveRiskAssessment;
use App\Modules\RisksAndComplaints\Actions\AssessRisk;
use App\Modules\RisksAndComplaints\Actions\AssignRisk;
use App\Modules\RisksAndComplaints\Actions\CloseRisk;
use App\Modules\RisksAndComplaints\Actions\CreateRisk;
use App\Modules\RisksAndComplaints\Actions\DeleteRisk;
use App\Modules\RisksAndComplaints\Actions\ListOperationalEntityTimeline;
use App\Modules\RisksAndComplaints\Actions\ListRisks;
use App\Modules\RisksAndComplaints\Actions\UpdateRisk;
use App\Modules\RisksAndComplaints\Actions\UpsertRiskTreatment;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Requests\AssessRiskRequest;
use App\Modules\RisksAndComplaints\Requests\AssignRiskRequest;
use App\Modules\RisksAndComplaints\Requests\CloseRiskRequest;
use App\Modules\RisksAndComplaints\Requests\CreateRiskRequest;
use App\Modules\RisksAndComplaints\Requests\UpdateRiskRequest;
use App\Modules\RisksAndComplaints\Requests\UpsertRiskTreatmentRequest;
use App\Modules\RisksAndComplaints\Resources\OperationalRiskResource;
use App\Modules\RisksAndComplaints\Resources\OperationalTimelineEntryResource;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SeasonContext $seasonContext,
    ) {}

    public function index(Request $request, ListRisks $action): JsonResponse
    {
        $this->authorize('viewAny', OperationalRisk::class);

        $paginator = $action->execute($this->seasonContext->require(), [
            'q' => $request->query('q'),
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'severity_level' => $request->query('severity_level'),
            'owner_employee_id' => $request->query('owner_employee_id'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: OperationalRiskResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateRiskRequest $request, CreateRisk $action): JsonResponse
    {
        $this->authorize('create', OperationalRisk::class);

        $risk = $action->execute(
            $request->user(),
            $this->seasonContext->require(),
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم إنشاء الخطر بنجاح',
            status: 201,
        );
    }

    public function show(OperationalRisk $risk): JsonResponse
    {
        $this->authorize('view', $risk);

        $risk->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);

        return ApiResponse::success(data: (new OperationalRiskResource($risk))->resolve());
    }

    public function update(
        UpdateRiskRequest $request,
        OperationalRisk $risk,
        UpdateRisk $action,
    ): JsonResponse {
        $this->authorize('update', $risk);

        $risk = $action->execute($request->user(), $risk, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم تحديث الخطر بنجاح',
        );
    }

    public function assess(
        AssessRiskRequest $request,
        OperationalRisk $risk,
        AssessRisk $action,
    ): JsonResponse {
        $this->authorize('assess', $risk);

        $risk = $action->execute($request->user(), $risk, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم تقييم الخطر',
        );
    }

    public function approveAssessment(
        Request $request,
        OperationalRisk $risk,
        ApproveRiskAssessment $action,
    ): JsonResponse {
        $this->authorize('approveAssessment', $risk);

        $risk = $action->execute($request->user(), $risk, $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم اعتماد تقييم الخطر',
        );
    }

    public function upsertTreatment(
        UpsertRiskTreatmentRequest $request,
        OperationalRisk $risk,
        UpsertRiskTreatment $action,
    ): JsonResponse {
        $this->authorize('manageTreatment', $risk);

        $risk = $action->execute($request->user(), $risk, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم حفظ خطة المعالجة',
        );
    }

    public function assign(
        AssignRiskRequest $request,
        OperationalRisk $risk,
        AssignRisk $action,
    ): JsonResponse {
        $this->authorize('assign', $risk);

        $risk = $action->execute($request->user(), $risk, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم تعيين مسؤول المعالجة',
        );
    }

    public function close(
        CloseRiskRequest $request,
        OperationalRisk $risk,
        CloseRisk $action,
    ): JsonResponse {
        $this->authorize('close', $risk);

        $risk = $action->execute($request->user(), $risk, $request->validated(), $request);

        return ApiResponse::success(
            data: (new OperationalRiskResource($risk))->resolve(),
            message: 'تم إغلاق الخطر',
        );
    }

    public function destroy(Request $request, OperationalRisk $risk, DeleteRisk $action): JsonResponse
    {
        $this->authorize('delete', $risk);

        $action->execute($request->user(), $risk, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الخطر',
        );
    }

    public function timeline(
        Request $request,
        OperationalRisk $risk,
        ListOperationalEntityTimeline $action,
    ): JsonResponse {
        $this->authorize('view', $risk);

        $entries = $action->execute(
            'operational_risk',
            (int) $risk->id,
            (int) $request->query('per_page', 50),
        );

        return ApiResponse::success(
            data: OperationalTimelineEntryResource::collection($entries)->resolve(),
        );
    }
}
