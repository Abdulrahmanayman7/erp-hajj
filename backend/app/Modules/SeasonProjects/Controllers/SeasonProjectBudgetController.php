<?php

namespace App\Modules\SeasonProjects\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonProjects\Actions\GetSeasonProjectBudget;
use App\Modules\SeasonProjects\Actions\LinkSeasonProjectPurchaseOrder;
use App\Modules\SeasonProjects\Actions\UnlinkSeasonProjectPurchaseOrder;
use App\Modules\SeasonProjects\Actions\UpdateSeasonProjectBudget;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SeasonProjects\Requests\LinkSeasonProjectPurchaseOrderRequest;
use App\Modules\SeasonProjects\Requests\UpdateSeasonProjectBudgetRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonProjectBudgetController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function show(SeasonProject $seasonProject, GetSeasonProjectBudget $action): JsonResponse
    {
        $this->assertSeason($seasonProject);
        $this->authorize('view', $seasonProject);

        return ApiResponse::success(data: $action->execute($seasonProject));
    }

    public function update(
        UpdateSeasonProjectBudgetRequest $request,
        SeasonProject $seasonProject,
        UpdateSeasonProjectBudget $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('manageBudget', $seasonProject);

        $data = $action->execute($request->user(), $seasonProject, $request->validated(), $request);

        return ApiResponse::success(data: $data, message: 'تم تحديث ميزانية المشروع');
    }

    public function linkPo(
        LinkSeasonProjectPurchaseOrderRequest $request,
        SeasonProject $seasonProject,
        LinkSeasonProjectPurchaseOrder $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('linkPo', $seasonProject);

        $poId = (int) $request->validated('purchase_order_id');
        $po = PurchaseOrder::query()->whereKey($poId)->first();
        if ($po === null) {
            abort(404);
        }
        $this->authorize('view', $po);

        $data = $action->execute(
            $request->user(),
            $seasonProject,
            $poId,
            $request->validated('notes') ?? null,
            $request,
        );

        return ApiResponse::success(data: $data, message: 'تم ربط أمر الشراء بالمشروع', status: 201);
    }

    public function unlinkPo(
        Request $request,
        SeasonProject $seasonProject,
        SeasonProjectBudgetPoLink $budgetPoLink,
        UnlinkSeasonProjectPurchaseOrder $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('linkPo', $seasonProject);

        if ((int) $budgetPoLink->project_id !== (int) $seasonProject->id
            || (int) $budgetPoLink->season_id !== (int) $seasonProject->season_id) {
            abort(404);
        }

        $data = $action->execute($request->user(), $seasonProject, $budgetPoLink, $request);

        return ApiResponse::success(data: $data, message: 'تم فك ارتباط أمر الشراء');
    }

    private function assertSeason(SeasonProject $project): void
    {
        $season = $this->context->require();
        if ((int) $project->season_id !== (int) $season->id) {
            abort(404);
        }
    }
}
