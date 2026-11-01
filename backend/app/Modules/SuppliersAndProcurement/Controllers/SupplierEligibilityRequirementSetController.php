<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\ApproveEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Actions\CreateEligibilityRequirementSetDraft;
use App\Modules\SuppliersAndProcurement\Actions\CreateEligibilityRequirementSetNewDraft;
use App\Modules\SuppliersAndProcurement\Actions\ListEligibilityRequirementSets;
use App\Modules\SuppliersAndProcurement\Actions\UpdateEligibilityRequirementSetDraft;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Requests\ApproveEligibilityRequirementSetRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreateEligibilityRequirementSetRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateEligibilityRequirementSetRequest;
use App\Modules\SuppliersAndProcurement\Resources\EligibilityRequirementSetResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierEligibilityRequirementSetController
{
    use AuthorizesRequests;

    public function index(Request $request, ListEligibilityRequirementSets $action): JsonResponse
    {
        $this->authorize('viewAny', SupplierEligibilityRequirementSet::class);

        $paginator = $action->execute([
            'config_key' => $request->query('config_key'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            EligibilityRequirementSetResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(SupplierEligibilityRequirementSet $requirementSet): JsonResponse
    {
        $this->authorize('view', $requirementSet);

        $requirementSet->load(['requirementDefs', 'author', 'lastEditor', 'approver']);

        return ApiResponse::success(
            data: (new EligibilityRequirementSetResource($requirementSet))->resolve(),
        );
    }

    public function store(
        CreateEligibilityRequirementSetRequest $request,
        CreateEligibilityRequirementSetDraft $action,
    ): JsonResponse {
        $this->authorize('create', SupplierEligibilityRequirementSet::class);

        $set = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new EligibilityRequirementSetResource($set))->resolve(),
            message: 'تم إنشاء مسودة إعدادات التأهيل',
            status: 201,
        );
    }

    public function update(
        UpdateEligibilityRequirementSetRequest $request,
        SupplierEligibilityRequirementSet $requirementSet,
        UpdateEligibilityRequirementSetDraft $action,
    ): JsonResponse {
        $this->authorize('update', $requirementSet);

        $set = $action->execute($request->user(), $requirementSet, $request->validated(), $request);

        return ApiResponse::success(
            data: (new EligibilityRequirementSetResource($set))->resolve(),
            message: 'تم تحديث مسودة إعدادات التأهيل',
        );
    }

    public function approve(
        ApproveEligibilityRequirementSetRequest $request,
        SupplierEligibilityRequirementSet $requirementSet,
        ApproveEligibilityRequirementSet $action,
    ): JsonResponse {
        $this->authorize('approve', $requirementSet);

        $set = $action->execute(
            $request->user(),
            $requirementSet,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new EligibilityRequirementSetResource($set))->resolve(),
            message: 'تم اعتماد إعدادات التأهيل',
        );
    }

    public function newDraft(
        Request $request,
        SupplierEligibilityRequirementSet $requirementSet,
        CreateEligibilityRequirementSetNewDraft $action,
    ): JsonResponse {
        $this->authorize('newDraft', $requirementSet);

        $set = $action->execute($request->user(), $requirementSet, $request);

        return ApiResponse::success(
            data: (new EligibilityRequirementSetResource($set))->resolve(),
            message: 'تم إنشاء مسودة جديدة من الإصدار المعتمد',
            status: 201,
        );
    }
}
