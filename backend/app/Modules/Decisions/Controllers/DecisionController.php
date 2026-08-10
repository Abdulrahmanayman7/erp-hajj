<?php

namespace App\Modules\Decisions\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Decisions\Actions\ApproveDecision;
use App\Modules\Decisions\Actions\CancelDecision;
use App\Modules\Decisions\Actions\CloseDecision;
use App\Modules\Decisions\Actions\CreateDecision;
use App\Modules\Decisions\Actions\DeleteDecision;
use App\Modules\Decisions\Actions\ListDecisions;
use App\Modules\Decisions\Actions\ReturnDecisionToDraft;
use App\Modules\Decisions\Actions\SubmitDecisionForApproval;
use App\Modules\Decisions\Actions\UpdateDecision;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Requests\CreateDecisionRequest;
use App\Modules\Decisions\Requests\DecisionCommentRequest;
use App\Modules\Decisions\Requests\ReturnDecisionToDraftRequest;
use App\Modules\Decisions\Requests\UpdateDecisionRequest;
use App\Modules\Decisions\Resources\DecisionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecisionController
{
    use AuthorizesRequests;

    public function index(Request $request, ListDecisions $action): JsonResponse
    {
        $this->authorize('viewAny', Decision::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'responsible_employee_id' => $request->query('responsible_employee_id'),
            'source_recommendation_id' => $request->query('source_recommendation_id'),
            'has_source_recommendation' => $request->query('has_source_recommendation'),
            'meeting_id' => $request->query('meeting_id'),
            'effective_date_from' => $request->query('effective_date_from'),
            'effective_date_to' => $request->query('effective_date_to'),
            'due_date_from' => $request->query('due_date_from'),
            'due_date_to' => $request->query('due_date_to'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: DecisionResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateDecisionRequest $request, CreateDecision $action): JsonResponse
    {
        $this->authorize('create', Decision::class);

        $decision = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم إنشاء القرار بنجاح',
            status: 201,
        );
    }

    public function show(Decision $decision): JsonResponse
    {
        $this->authorize('view', $decision);

        $decision->load([
            'organizationUnit',
            'issuedByEmployee',
            'responsibleEmployee',
            'createdBy',
            'sourceRecommendation.meeting',
            'statusTransitions.performer',
        ]);

        return ApiResponse::success(data: (new DecisionResource($decision))->resolve());
    }

    public function update(
        UpdateDecisionRequest $request,
        Decision $decision,
        UpdateDecision $action,
    ): JsonResponse {
        $this->authorize('update', $decision);

        $decision = $action->execute($request->user(), $decision, $request->validated(), $request);

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم تحديث القرار بنجاح',
        );
    }

    public function destroy(Request $request, Decision $decision, DeleteDecision $action): JsonResponse
    {
        $this->authorize('delete', $decision);

        $action->execute($request->user(), $decision, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف القرار',
        );
    }

    public function submit(
        DecisionCommentRequest $request,
        Decision $decision,
        SubmitDecisionForApproval $action,
    ): JsonResponse {
        $this->authorize('update', $decision);

        $decision = $action->execute(
            $request->user(),
            $decision,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم إرسال القرار للاعتماد',
        );
    }

    public function returnDraft(
        ReturnDecisionToDraftRequest $request,
        Decision $decision,
        ReturnDecisionToDraft $action,
    ): JsonResponse {
        $this->authorize('approve', $decision);

        $decision = $action->execute(
            $request->user(),
            $decision,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم إرجاع القرار إلى المسودة',
        );
    }

    public function approve(
        DecisionCommentRequest $request,
        Decision $decision,
        ApproveDecision $action,
    ): JsonResponse {
        $this->authorize('approve', $decision);

        $decision = $action->execute(
            $request->user(),
            $decision,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم اعتماد القرار',
        );
    }

    public function cancel(
        DecisionCommentRequest $request,
        Decision $decision,
        CancelDecision $action,
    ): JsonResponse {
        $this->authorize('update', $decision);

        $decision = $action->execute(
            $request->user(),
            $decision,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم إلغاء القرار',
        );
    }

    public function close(
        DecisionCommentRequest $request,
        Decision $decision,
        CloseDecision $action,
    ): JsonResponse {
        $this->authorize('close', $decision);

        $decision = $action->execute(
            $request->user(),
            $decision,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DecisionResource($decision))->resolve(),
            message: 'تم إغلاق القرار',
        );
    }
}
