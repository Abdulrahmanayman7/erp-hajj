<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\Transportation\Actions\Breakdowns\CancelBreakdown;
use App\Modules\Transportation\Actions\Breakdowns\CloseBreakdown;
use App\Modules\Transportation\Actions\Breakdowns\ExecuteReplacement;
use App\Modules\Transportation\Actions\Breakdowns\PreviewReplacement;
use App\Modules\Transportation\Actions\Breakdowns\RecordBreakdown;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Requests\ExecuteReplacementRequest;
use App\Modules\Transportation\Requests\StoreBreakdownRequest;
use App\Modules\Transportation\Requests\TransportReasonRequest;
use App\Modules\Transportation\Resources\TransportVehicleBreakdownResource;
use App\Modules\Transportation\Resources\TransportVehicleReplacementResource;
use App\Modules\Transportation\Resources\TransportVehicleRunResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped breakdown / replacement APIs (ADR-0021 D03).
 */
class TransportBreakdownController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportVehicleBreakdown::class);

        $query = TransportVehicleBreakdown::query()
            ->where('season_id', $this->context->require()->id)
            ->with(['seasonVehicle.vehicle', 'run', 'replacement.passengerMoves'])
            ->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, BreakdownStatus::values(), true)) {
            $query->where('status', $status);
        }

        $runId = $request->query('vehicle_run_id');
        if (is_numeric($runId)) {
            $query->where('vehicle_run_id', (int) $runId);
        }

        $paginator = $query->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportVehicleBreakdownResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreBreakdownRequest $request, TransportVehicleRun $run, RecordBreakdown $action): JsonResponse
    {
        $this->authorize('operate', $run);

        $breakdown = $action->execute($request->user(), $run, $request->validated(), $request);
        $breakdown->load(['seasonVehicle.vehicle', 'run', 'replacement']);

        return ApiResponse::success(
            data: (new TransportVehicleBreakdownResource($breakdown))->resolve(),
            message: 'تم تسجيل عطل المركبة',
            status: 201,
        );
    }

    public function show(TransportVehicleBreakdown $breakdown): JsonResponse
    {
        $this->authorize('view', $breakdown);
        $breakdown->load(['seasonVehicle.vehicle', 'run', 'replacement.passengerMoves']);

        return ApiResponse::success(data: (new TransportVehicleBreakdownResource($breakdown))->resolve());
    }

    public function close(TransportReasonRequest $request, TransportVehicleBreakdown $breakdown, CloseBreakdown $action): JsonResponse
    {
        $this->authorize('operate', $breakdown);
        $updated = $action->execute($request->user(), $breakdown, (string) $request->validated('reason'), $request);
        $updated->load(['seasonVehicle.vehicle', 'run', 'replacement']);

        return ApiResponse::success(data: (new TransportVehicleBreakdownResource($updated))->resolve(), message: 'تم إغلاق العطل');
    }

    public function cancel(TransportReasonRequest $request, TransportVehicleBreakdown $breakdown, CancelBreakdown $action): JsonResponse
    {
        $this->authorize('operate', $breakdown);
        $updated = $action->execute($request->user(), $breakdown, (string) $request->validated('reason'), $request);
        $updated->load(['seasonVehicle.vehicle', 'run', 'replacement']);

        return ApiResponse::success(data: (new TransportVehicleBreakdownResource($updated))->resolve(), message: 'تم إلغاء تسجيل العطل');
    }

    public function preview(Request $request, TransportVehicleBreakdown $breakdown, PreviewReplacement $action): JsonResponse
    {
        $this->authorize('view', $breakdown);
        $replacementId = (int) $request->query('replacement_season_vehicle_id');
        if ($replacementId < 1) {
            return ApiResponse::error(
                'المركبة البديلة مطلوبة',
                'VALIDATION',
                ['replacement_season_vehicle_id' => ['المركبة البديلة مطلوبة']],
                422,
            );
        }

        return ApiResponse::success(data: $action->execute($breakdown, $replacementId));
    }

    public function replace(
        ExecuteReplacementRequest $request,
        TransportVehicleBreakdown $breakdown,
        ExecuteReplacement $action,
    ): JsonResponse {
        $this->authorize('operate', $breakdown);
        $result = $action->execute($request->user(), $breakdown, $request->validated(), $request);
        $result['breakdown']->load(['seasonVehicle.vehicle', 'run', 'replacement.passengerMoves']);

        return ApiResponse::success(
            data: [
                'breakdown' => (new TransportVehicleBreakdownResource($result['breakdown']))->resolve(),
                'replacement' => (new TransportVehicleReplacementResource($result['replacement']))->resolve(),
                'run' => (new TransportVehicleRunResource($result['run']))->resolve(),
            ],
            message: 'تم استبدال المركبة',
        );
    }
}
