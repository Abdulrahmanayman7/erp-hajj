<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\Transportation\Actions\Movements\CancelMovement;
use App\Modules\Transportation\Actions\Movements\CreateMovement;
use App\Modules\Transportation\Actions\Movements\MovementLoader;
use App\Modules\Transportation\Actions\Movements\TransitionMovement;
use App\Modules\Transportation\Actions\Movements\UpdateMovement;
use App\Modules\Transportation\Actions\Runs\CreateRun;
use App\Modules\Transportation\Actions\Runs\RunLoader;
use App\Modules\Transportation\Enums\MovementStatus;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use App\Modules\Transportation\Requests\OperationalFactRequest;
use App\Modules\Transportation\Requests\StoreMovementRequest;
use App\Modules\Transportation\Requests\StoreRunRequest;
use App\Modules\Transportation\Requests\TransportReasonRequest;
use App\Modules\Transportation\Resources\TransportMovementResource;
use App\Modules\Transportation\Resources\TransportTimelineEventResource;
use App\Modules\Transportation\Resources\TransportVehicleRunResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class TransportMovementController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportMovement::class);

        $query = TransportMovement::query()
            ->where('season_id', $this->context->require()->id)
            ->with(['origin', 'destination'])
            ->withCount('runs');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, MovementStatus::values(), true)) {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy('planned_departure_at')->orderBy('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportMovementResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreMovementRequest $request, CreateMovement $action): JsonResponse
    {
        $this->authorize('create', TransportMovement::class);

        $movement = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportMovementResource(MovementLoader::load($movement)))->resolve(), message: 'تم إنشاء الحركة', status: 201);
    }

    public function show(TransportMovement $movement): JsonResponse
    {
        $this->authorize('view', $movement);

        return ApiResponse::success(data: (new TransportMovementResource(MovementLoader::load($movement)))->resolve());
    }

    public function update(StoreMovementRequest $request, TransportMovement $movement, UpdateMovement $action): JsonResponse
    {
        $this->authorize('update', $movement);

        $updated = $action->execute($request->user(), $movement, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportMovementResource(MovementLoader::load($updated)))->resolve(), message: 'تم تحديث الحركة');
    }

    public function plan(OperationalFactRequest $request, TransportMovement $movement, TransitionMovement $action): JsonResponse
    {
        $this->authorize('update', $movement);

        return $this->transition($request, $movement, $action, TransitionMovement::PLAN, 'تم اعتماد خطة الحركة');
    }

    public function start(OperationalFactRequest $request, TransportMovement $movement, TransitionMovement $action): JsonResponse
    {
        $this->authorize('operate', $movement);

        return $this->transition($request, $movement, $action, TransitionMovement::START, 'تم بدء تنفيذ الحركة');
    }

    public function complete(OperationalFactRequest $request, TransportMovement $movement, TransitionMovement $action): JsonResponse
    {
        $this->authorize('operate', $movement);

        return $this->transition($request, $movement, $action, TransitionMovement::COMPLETE, 'تم إكمال الحركة');
    }

    public function cancel(TransportReasonRequest $request, TransportMovement $movement, CancelMovement $action): JsonResponse
    {
        $this->authorize('update', $movement);

        $updated = $action->execute($request->user(), $movement, (string) $request->validated('reason'), $request);

        return ApiResponse::success(data: (new TransportMovementResource(MovementLoader::load($updated)))->resolve(), message: 'تم إلغاء الحركة');
    }

    public function timeline(TransportMovement $movement): JsonResponse
    {
        $this->authorize('view', $movement);

        $tenantId = (int) $movement->tenant_id;
        $events = TransportTimelineEvent::query()
            ->where('transport_movement_id', $movement->id)
            ->select('transport_timeline_events.*')
            ->selectSub(
                fn ($q) => $q->from('transport_timeline_events as corrections')
                    ->select('corrections.id')
                    ->where('corrections.tenant_id', $tenantId)
                    ->whereColumn('corrections.supersedes_event_id', 'transport_timeline_events.id')
                    ->limit(1),
                'superseded_by_event_id',
            )
            ->with('actor')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(data: TransportTimelineEventResource::collection($events)->resolve());
    }

    public function storeRun(StoreRunRequest $request, TransportMovement $movement, CreateRun $action): JsonResponse
    {
        $this->authorize('update', $movement);

        $run = $action->execute($request->user(), $movement, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportVehicleRunResource(RunLoader::load($run)))->resolve(), message: 'تم إنشاء الرحلة', status: 201);
    }

    private function transition(OperationalFactRequest $request, TransportMovement $movement, TransitionMovement $action, string $transition, string $message): JsonResponse
    {
        $updated = $action->execute(
            $request->user(),
            $movement,
            $transition,
            $request->validated('occurred_at'),
            $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(data: (new TransportMovementResource(MovementLoader::load($updated)))->resolve(), message: $message);
    }
}
