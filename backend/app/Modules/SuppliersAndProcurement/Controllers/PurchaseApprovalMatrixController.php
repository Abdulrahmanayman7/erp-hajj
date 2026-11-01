<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\DeletePurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\DeletePurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Requests\CreatePurchaseApprovalMatrixRequest;
use App\Modules\SuppliersAndProcurement\Requests\PublishPurchaseApprovalMatrixRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePurchaseApprovalMatrixRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpsertPurchaseApprovalMatrixLevelRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpsertPurchaseApprovalMatrixRuleRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseApprovalMatrixResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseApprovalMatrixController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseApprovalMatrix::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $query = PurchaseApprovalMatrix::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $paginator = $query->with(['rules', 'levels', 'creator', 'publisher'])->paginate($perPage);

        return SupplierApiResponse::paginated(
            PurchaseApprovalMatrixResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(
        CreatePurchaseApprovalMatrixRequest $request,
        CreatePurchaseApprovalMatrix $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseApprovalMatrix::class);

        $matrix = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($matrix))->resolve(),
            message: 'تم إنشاء مسودة مصفوفة الاعتماد',
            status: 201,
        );
    }

    public function show(PurchaseApprovalMatrix $purchase_approval_matrix): JsonResponse
    {
        $this->authorize('view', $purchase_approval_matrix);

        $purchase_approval_matrix->load(['rules', 'levels', 'creator', 'publisher']);

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($purchase_approval_matrix))->resolve(),
        );
    }

    public function update(
        UpdatePurchaseApprovalMatrixRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        UpdatePurchaseApprovalMatrix $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $matrix = $action->execute($request->user(), $purchase_approval_matrix, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($matrix))->resolve(),
            message: 'تم تحديث مسودة مصفوفة الاعتماد',
        );
    }

    public function publish(
        PublishPurchaseApprovalMatrixRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        PublishPurchaseApprovalMatrix $action,
    ): JsonResponse {
        $this->authorize('publish', $purchase_approval_matrix);

        $matrix = $action->execute(
            $request->user(),
            $purchase_approval_matrix,
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($matrix))->resolve(),
            message: 'تم نشر مصفوفة الاعتماد',
        );
    }

    public function indexRules(PurchaseApprovalMatrix $purchase_approval_matrix): JsonResponse
    {
        $this->authorize('view', $purchase_approval_matrix);

        $purchase_approval_matrix->load('rules');

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($purchase_approval_matrix))->resolve()['rules'] ?? [],
        );
    }

    public function storeRule(
        UpsertPurchaseApprovalMatrixRuleRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        UpsertPurchaseApprovalMatrixRule $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $rule = $action->execute($request->user(), $purchase_approval_matrix, $request->validated(), $request);
        $purchase_approval_matrix->load(['rules', 'levels', 'creator', 'publisher']);

        return ApiResponse::success(
            data: [
                'rule' => [
                    'id' => $rule->id,
                    'min_amount' => $rule->min_amount !== null ? (string) $rule->min_amount : null,
                    'max_amount' => $rule->max_amount !== null ? (string) $rule->max_amount : null,
                    'request_kind' => $rule->request_kind?->value,
                    'organization_unit_id' => $rule->organization_unit_id,
                    'season_id' => $rule->season_id,
                    'applies_when_unpriced' => (bool) $rule->applies_when_unpriced,
                    'specificity_hint' => $rule->specificity_hint,
                ],
                'matrix' => (new PurchaseApprovalMatrixResource($purchase_approval_matrix->fresh(['rules', 'levels', 'creator', 'publisher'])))->resolve(),
            ],
            message: 'تم إضافة قاعدة الاعتماد',
            status: 201,
        );
    }

    public function updateRule(
        UpsertPurchaseApprovalMatrixRuleRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        PurchaseApprovalMatrixRule $matrix_rule,
        UpsertPurchaseApprovalMatrixRule $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $rule = $action->execute(
            $request->user(),
            $purchase_approval_matrix,
            $request->validated(),
            $request,
            $matrix_rule,
        );

        return ApiResponse::success(
            data: [
                'id' => $rule->id,
                'min_amount' => $rule->min_amount !== null ? (string) $rule->min_amount : null,
                'max_amount' => $rule->max_amount !== null ? (string) $rule->max_amount : null,
                'request_kind' => $rule->request_kind?->value,
                'organization_unit_id' => $rule->organization_unit_id,
                'season_id' => $rule->season_id,
                'applies_when_unpriced' => (bool) $rule->applies_when_unpriced,
                'specificity_hint' => $rule->specificity_hint,
            ],
            message: 'تم تحديث قاعدة الاعتماد',
        );
    }

    public function destroyRule(
        Request $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        PurchaseApprovalMatrixRule $matrix_rule,
        DeletePurchaseApprovalMatrixRule $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $action->execute($request->user(), $purchase_approval_matrix, $matrix_rule, $request);

        return ApiResponse::success(message: 'تم حذف قاعدة الاعتماد');
    }

    public function indexLevels(PurchaseApprovalMatrix $purchase_approval_matrix): JsonResponse
    {
        $this->authorize('view', $purchase_approval_matrix);

        $purchase_approval_matrix->load('levels');

        return ApiResponse::success(
            data: (new PurchaseApprovalMatrixResource($purchase_approval_matrix))->resolve()['levels'] ?? [],
        );
    }

    public function storeLevel(
        UpsertPurchaseApprovalMatrixLevelRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        UpsertPurchaseApprovalMatrixLevel $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $level = $action->execute($request->user(), $purchase_approval_matrix, $request->validated(), $request);

        return ApiResponse::success(
            data: [
                'id' => $level->id,
                'level_order' => $level->level_order,
                'name' => $level->name,
            ],
            message: 'تم إضافة مستوى الاعتماد',
            status: 201,
        );
    }

    public function updateLevel(
        UpsertPurchaseApprovalMatrixLevelRequest $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        PurchaseApprovalMatrixLevel $matrix_level,
        UpsertPurchaseApprovalMatrixLevel $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $level = $action->execute(
            $request->user(),
            $purchase_approval_matrix,
            $request->validated(),
            $request,
            $matrix_level,
        );

        return ApiResponse::success(
            data: [
                'id' => $level->id,
                'level_order' => $level->level_order,
                'name' => $level->name,
            ],
            message: 'تم تحديث مستوى الاعتماد',
        );
    }

    public function destroyLevel(
        Request $request,
        PurchaseApprovalMatrix $purchase_approval_matrix,
        PurchaseApprovalMatrixLevel $matrix_level,
        DeletePurchaseApprovalMatrixLevel $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_approval_matrix);

        $action->execute($request->user(), $purchase_approval_matrix, $matrix_level, $request);

        return ApiResponse::success(message: 'تم حذف مستوى الاعتماد');
    }
}
