<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\Transportation\Actions\SeasonFleet\AddSeasonVehicle;
use App\Modules\Transportation\Actions\SeasonFleet\RemoveSeasonVehicle;
use App\Modules\Transportation\Actions\SeasonFleet\SeasonVehicleLoader;
use App\Modules\Transportation\Actions\SeasonFleet\SetSeasonVehicleActive;
use App\Modules\Transportation\Actions\SeasonFleet\SetSeasonVehicleDefaultCrew;
use App\Modules\Transportation\Actions\SeasonFleet\UpdateSeasonVehicle;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Requests\DefaultCrewRequest;
use App\Modules\Transportation\Requests\StoreSeasonVehicleRequest;
use App\Modules\Transportation\Resources\TransportSeasonVehicleResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class TransportSeasonVehicleController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportSeasonVehicle::class);

        $query = TransportSeasonVehicle::query()
            ->where('season_id', $this->context->require()->id)
            ->with(['vehicle', 'defaultDriverEmployee', 'defaultExternalDriver', 'defaultSupervisorEmployee']);

        $isActive = $request->query('is_active');
        if ($isActive !== null && $isActive !== '' && ($flag = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) !== null) {
            $query->where('is_active', $flag);
        }
        if (is_string($level = $request->query('service_level')) && $level !== '') {
            $query->where('service_level', $level);
        }

        $paginator = $query->orderBy('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportSeasonVehicleResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function store(StoreSeasonVehicleRequest $request, AddSeasonVehicle $action): JsonResponse
    {
        $this->authorize('create', TransportSeasonVehicle::class);

        $seasonVehicle = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(data: (new TransportSeasonVehicleResource($seasonVehicle))->resolve(), message: 'تمت إضافة المركبة إلى أسطول الموسم', status: 201);
    }

    public function show(TransportSeasonVehicle $seasonVehicle): JsonResponse
    {
        $this->authorize('view', $seasonVehicle);

        return ApiResponse::success(data: (new TransportSeasonVehicleResource(SeasonVehicleLoader::load($seasonVehicle)))->resolve());
    }

    public function update(StoreSeasonVehicleRequest $request, TransportSeasonVehicle $seasonVehicle, UpdateSeasonVehicle $action): JsonResponse
    {
        $this->authorize('update', $seasonVehicle);

        $updated = $action->execute($request->user(), $seasonVehicle, $request->validated(), $request);

        return ApiResponse::success(data: (new TransportSeasonVehicleResource($updated))->resolve(), message: 'تم تحديث مركبة الموسم');
    }

    public function destroy(Request $request, TransportSeasonVehicle $seasonVehicle, RemoveSeasonVehicle $action): JsonResponse
    {
        $this->authorize('delete', $seasonVehicle);

        $action->execute($request->user(), $seasonVehicle, $request);

        return ApiResponse::success(data: null, message: 'تمت إزالة المركبة من أسطول الموسم');
    }

    public function defaultCrew(DefaultCrewRequest $request, TransportSeasonVehicle $seasonVehicle, SetSeasonVehicleDefaultCrew $action): JsonResponse
    {
        $this->authorize('update', $seasonVehicle);

        $toInt = fn (mixed $value): ?int => $value !== null ? (int) $value : null;
        $updated = $action->execute(
            $request->user(),
            $seasonVehicle,
            $toInt($request->validated('default_driver_employee_id')),
            $toInt($request->validated('default_external_driver_id')),
            $toInt($request->validated('default_supervisor_employee_id')),
            $request,
        );

        return ApiResponse::success(data: (new TransportSeasonVehicleResource($updated))->resolve(), message: 'تم تحديث الطاقم الافتراضي');
    }

    public function activate(Request $request, TransportSeasonVehicle $seasonVehicle, SetSeasonVehicleActive $action): JsonResponse
    {
        return $this->setActive($request, $seasonVehicle, $action, true);
    }

    public function deactivate(Request $request, TransportSeasonVehicle $seasonVehicle, SetSeasonVehicleActive $action): JsonResponse
    {
        return $this->setActive($request, $seasonVehicle, $action, false);
    }

    private function setActive(Request $request, TransportSeasonVehicle $seasonVehicle, SetSeasonVehicleActive $action, bool $active): JsonResponse
    {
        $this->authorize('update', $seasonVehicle);

        $updated = $action->execute($request->user(), $seasonVehicle, $active, $request);

        return ApiResponse::success(data: (new TransportSeasonVehicleResource($updated))->resolve(), message: $active ? 'تم تفعيل مركبة الموسم' : 'تم إيقاف مركبة الموسم');
    }
}
