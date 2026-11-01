<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Actions\CreateNewDraftCriteriaVersion;
use App\Modules\SuppliersAndProcurement\Actions\DeleteEvaluationCriterion;
use App\Modules\SuppliersAndProcurement\Actions\ListEvaluationCriteriaPacks;
use App\Modules\SuppliersAndProcurement\Actions\PublishEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Actions\UpdateEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Actions\UpsertEvaluationCriterion;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use App\Modules\SuppliersAndProcurement\Requests\CreateEvaluationCriteriaPackRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateEvaluationCriteriaPackRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpsertEvaluationCriterionRequest;
use App\Modules\SuppliersAndProcurement\Resources\EvaluationCriteriaPackResource;
use App\Modules\SuppliersAndProcurement\Resources\EvaluationCriterionResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierEvaluationCriteriaPackController
{
    use AuthorizesRequests;

    public function index(Request $request, ListEvaluationCriteriaPacks $action): JsonResponse
    {
        $this->authorize('viewAny', SupplierEvaluationCriteriaPack::class);

        $paginator = $action->execute($request->user(), [
            'supplier_category_id' => $request->query('supplier_category_id'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            EvaluationCriteriaPackResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(SupplierEvaluationCriteriaPack $criteria_pack): JsonResponse
    {
        $this->authorize('view', $criteria_pack);

        $criteria_pack->load(['criteria', 'preparer', 'lastEditor', 'approver', 'category']);

        return ApiResponse::success(
            data: (new EvaluationCriteriaPackResource($criteria_pack))->resolve(),
        );
    }

    public function store(
        CreateEvaluationCriteriaPackRequest $request,
        CreateEvaluationCriteriaPack $action,
    ): JsonResponse {
        $this->authorize('create', SupplierEvaluationCriteriaPack::class);

        $pack = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new EvaluationCriteriaPackResource($pack))->resolve(),
            message: 'تم إنشاء مسودة حزمة معايير التقييم',
            status: 201,
        );
    }

    public function update(
        UpdateEvaluationCriteriaPackRequest $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        UpdateEvaluationCriteriaPack $action,
    ): JsonResponse {
        $this->authorize('update', $criteria_pack);

        $pack = $action->execute($request->user(), $criteria_pack, $request->validated(), $request);

        return ApiResponse::success(
            data: (new EvaluationCriteriaPackResource($pack))->resolve(),
            message: 'تم تحديث مسودة حزمة معايير التقييم',
        );
    }

    public function publish(
        Request $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        PublishEvaluationCriteriaPack $action,
    ): JsonResponse {
        $this->authorize('publish', $criteria_pack);

        $pack = $action->execute($request->user(), $criteria_pack, $request);

        return ApiResponse::success(
            data: (new EvaluationCriteriaPackResource($pack))->resolve(),
            message: 'تم نشر حزمة معايير التقييم',
        );
    }

    public function newDraft(
        Request $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        CreateNewDraftCriteriaVersion $action,
    ): JsonResponse {
        $this->authorize('newDraft', $criteria_pack);

        $pack = $action->execute($request->user(), $criteria_pack, $request);

        return ApiResponse::success(
            data: (new EvaluationCriteriaPackResource($pack))->resolve(),
            message: 'تم إنشاء مسودة إصدار جديد من حزمة المعايير',
            status: 201,
        );
    }

    public function indexCriteria(SupplierEvaluationCriteriaPack $criteria_pack): JsonResponse
    {
        $this->authorize('view', $criteria_pack);

        $criteria_pack->load('criteria');

        return ApiResponse::success(
            data: EvaluationCriterionResource::collection($criteria_pack->criteria)->resolve(),
        );
    }

    public function storeCriterion(
        UpsertEvaluationCriterionRequest $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        UpsertEvaluationCriterion $action,
    ): JsonResponse {
        $this->authorize('manageCriteria', $criteria_pack);

        $line = $action->execute($request->user(), $criteria_pack, $request->validated(), $request);

        return ApiResponse::success(
            data: (new EvaluationCriterionResource($line))->resolve(),
            message: 'تم إضافة معيار التقييم',
            status: 201,
        );
    }

    public function updateCriterion(
        UpsertEvaluationCriterionRequest $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        SupplierEvaluationCriterion $criterion,
        UpsertEvaluationCriterion $action,
    ): JsonResponse {
        $this->authorize('manageCriteria', $criteria_pack);

        $line = $action->execute(
            $request->user(),
            $criteria_pack,
            $request->validated(),
            $request,
            $criterion,
        );

        return ApiResponse::success(
            data: (new EvaluationCriterionResource($line))->resolve(),
            message: 'تم تحديث معيار التقييم',
        );
    }

    public function destroyCriterion(
        Request $request,
        SupplierEvaluationCriteriaPack $criteria_pack,
        SupplierEvaluationCriterion $criterion,
        DeleteEvaluationCriterion $action,
    ): JsonResponse {
        $this->authorize('manageCriteria', $criteria_pack);

        $action->execute($request->user(), $criteria_pack, $criterion, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف معيار التقييم',
        );
    }
}
