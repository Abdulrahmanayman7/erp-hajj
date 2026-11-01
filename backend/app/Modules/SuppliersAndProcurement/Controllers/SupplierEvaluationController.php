<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\ApproveSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Actions\FileSupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Actions\ListSupplierEvaluationAppeals;
use App\Modules\SuppliersAndProcurement\Actions\ListSupplierEvaluations;
use App\Modules\SuppliersAndProcurement\Actions\RejectSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Actions\SubmitSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Actions\UpdateSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Requests\ApproveSupplierEvaluationRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierEvaluationRequest;
use App\Modules\SuppliersAndProcurement\Requests\FileSupplierEvaluationAppealRequest;
use App\Modules\SuppliersAndProcurement\Requests\RejectSupplierEvaluationRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateSupplierEvaluationRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierEvaluationAppealResource;
use App\Modules\SuppliersAndProcurement\Resources\SupplierEvaluationResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierEvaluationController
{
    use AuthorizesRequests;

    public function index(Request $request, ListSupplierEvaluations $action): JsonResponse
    {
        $this->authorize('viewAny', SupplierEvaluation::class);

        $paginator = $action->execute($request->user(), [
            'supplier_id' => $request->query('supplier_id'),
            'supplier_category_id' => $request->query('supplier_category_id'),
            'evaluation_kind' => $request->query('evaluation_kind'),
            'status' => $request->query('status'),
            'season_id' => $request->query('season_id'),
            'monitoring_recommended' => $request->query('monitoring_recommended'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            SupplierEvaluationResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(SupplierEvaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        $evaluation->load([
            'scoreSnapshots',
            'preparer',
            'approver',
            'rejector',
            'category',
            'supplier',
            'season',
            'criteriaPack',
        ]);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
        );
    }

    public function store(
        CreateSupplierEvaluationRequest $request,
        CreateSupplierEvaluation $action,
    ): JsonResponse {
        $this->authorize('create', SupplierEvaluation::class);

        $evaluation = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
            message: 'تم إنشاء مسودة تقييم المورد',
            status: 201,
        );
    }

    public function update(
        UpdateSupplierEvaluationRequest $request,
        SupplierEvaluation $evaluation,
        UpdateSupplierEvaluation $action,
    ): JsonResponse {
        $this->authorize('update', $evaluation);

        $evaluation = $action->execute($request->user(), $evaluation, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
            message: 'تم تحديث مسودة تقييم المورد',
        );
    }

    public function submit(
        Request $request,
        SupplierEvaluation $evaluation,
        SubmitSupplierEvaluation $action,
    ): JsonResponse {
        $this->authorize('submit', $evaluation);

        $evaluation = $action->execute($request->user(), $evaluation, $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
            message: 'تم إرسال التقييم للاعتماد',
        );
    }

    public function approve(
        ApproveSupplierEvaluationRequest $request,
        SupplierEvaluation $evaluation,
        ApproveSupplierEvaluation $action,
    ): JsonResponse {
        $this->authorize('approve', $evaluation);

        $evaluation = $action->execute($request->user(), $evaluation, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
            message: 'تم اعتماد التقييم',
        );
    }

    public function reject(
        RejectSupplierEvaluationRequest $request,
        SupplierEvaluation $evaluation,
        RejectSupplierEvaluation $action,
    ): JsonResponse {
        $this->authorize('reject', $evaluation);

        $evaluation = $action->execute($request->user(), $evaluation, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationResource($evaluation))->resolve(),
            message: 'تم رفض التقييم',
        );
    }

    public function indexAppeals(
        SupplierEvaluation $evaluation,
        ListSupplierEvaluationAppeals $action,
    ): JsonResponse {
        $this->authorize('viewAppeals', $evaluation);

        $appeals = $action->execute($evaluation);

        return ApiResponse::success(
            data: SupplierEvaluationAppealResource::collection($appeals)->resolve(),
        );
    }

    public function storeAppeal(
        FileSupplierEvaluationAppealRequest $request,
        SupplierEvaluation $evaluation,
        FileSupplierEvaluationAppeal $action,
    ): JsonResponse {
        $this->authorize('fileAppeal', $evaluation);

        $appeal = $action->execute($request->user(), $evaluation, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationAppealResource($appeal))->resolve(),
            message: 'تم تسجيل اعتراض التقييم',
            status: 201,
        );
    }
}
