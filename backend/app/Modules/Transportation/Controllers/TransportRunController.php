<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\Passengers\AddPassengers;
use App\Modules\Transportation\Actions\Passengers\RecordPassengerOutcome;
use App\Modules\Transportation\Actions\Runs\AssignRunDriver;
use App\Modules\Transportation\Actions\Runs\AssignRunSupervisor;
use App\Modules\Transportation\Actions\Runs\AssignRunVehicle;
use App\Modules\Transportation\Actions\Runs\CancelRun;
use App\Modules\Transportation\Actions\Runs\RunLoader;
use App\Modules\Transportation\Actions\Runs\TransitionRun;
use App\Modules\Transportation\Actions\Runs\UpdateRun;
use App\Modules\Transportation\Enums\AssignmentSource;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Requests\AddPassengersRequest;
use App\Modules\Transportation\Requests\AssignRunDriverRequest;
use App\Modules\Transportation\Requests\AssignRunSupervisorRequest;
use App\Modules\Transportation\Requests\AssignRunVehicleRequest;
use App\Modules\Transportation\Requests\OperationalFactRequest;
use App\Modules\Transportation\Requests\PassengerOutcomeRequest;
use App\Modules\Transportation\Requests\TransportReasonRequest;
use App\Modules\Transportation\Requests\UpdateRunRequest;
use App\Modules\Transportation\Resources\TransportRunPassengerResource;
use App\Modules\Transportation\Resources\TransportVehicleRunResource;
use App\Modules\Transportation\Support\PassengerEntitlementStatuses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class TransportRunController
{
    use AuthorizesRequests;

    public function show(TransportVehicleRun $run): JsonResponse
    {
        $this->authorize('view', $run);

        return $this->runResponse($run);
    }

    public function update(UpdateRunRequest $request, TransportVehicleRun $run, UpdateRun $action): JsonResponse
    {
        $this->authorize('update', $run);

        return $this->runResponse($action->execute($request->user(), $run, $request->validated(), $request), 'تم تحديث الرحلة');
    }

    public function startBoarding(OperationalFactRequest $request, TransportVehicleRun $run, TransitionRun $action): JsonResponse
    {
        return $this->transition($request, $run, $action, TransitionRun::START_BOARDING, 'تم بدء الصعود');
    }

    public function depart(OperationalFactRequest $request, TransportVehicleRun $run, TransitionRun $action): JsonResponse
    {
        return $this->transition($request, $run, $action, TransitionRun::DEPART, 'تم تسجيل الانطلاق');
    }

    public function arrive(OperationalFactRequest $request, TransportVehicleRun $run, TransitionRun $action): JsonResponse
    {
        return $this->transition($request, $run, $action, TransitionRun::ARRIVE, 'تم تسجيل الوصول');
    }

    public function complete(OperationalFactRequest $request, TransportVehicleRun $run, TransitionRun $action): JsonResponse
    {
        return $this->transition($request, $run, $action, TransitionRun::COMPLETE, 'تم إكمال الرحلة');
    }

    public function cancel(TransportReasonRequest $request, TransportVehicleRun $run, CancelRun $action): JsonResponse
    {
        $this->authorize('operate', $run);

        return $this->runResponse($action->execute($request->user(), $run, (string) $request->validated('reason'), $request), 'تم إلغاء الرحلة');
    }

    public function assignVehicle(AssignRunVehicleRequest $request, TransportVehicleRun $run, AssignRunVehicle $action): JsonResponse
    {
        $this->authorize('operate', $run);

        $updated = $action->execute($request->user(), $run, (int) $request->validated('transport_season_vehicle_id'), $request->validated('reason'), $request);

        return $this->runResponse($updated, 'تم تعيين المركبة');
    }

    public function assignDriver(AssignRunDriverRequest $request, TransportVehicleRun $run, AssignRunDriver $action): JsonResponse
    {
        $this->authorize('operate', $run);

        $employeeId = $request->validated('driver_employee_id');
        $externalId = $request->validated('external_driver_id');
        $updated = $action->execute(
            $request->user(),
            $run,
            $employeeId !== null ? (int) $employeeId : null,
            $externalId !== null ? (int) $externalId : null,
            $request->validated('reason'),
            $request,
        );

        return $this->runResponse($updated, 'تم تعيين السائق');
    }

    public function assignSupervisor(AssignRunSupervisorRequest $request, TransportVehicleRun $run, AssignRunSupervisor $action): JsonResponse
    {
        $this->authorize('operate', $run);

        $employeeId = $request->validated('supervisor_employee_id');
        $updated = $action->execute($request->user(), $run, $employeeId !== null ? (int) $employeeId : null, $request->validated('reason'), $request);

        return $this->runResponse($updated, $employeeId !== null ? 'تم تعيين مشرف النقل' : 'تم إنهاء تكليف مشرف النقل');
    }

    public function passengers(Request $request, TransportVehicleRun $run, PassengerEntitlementStatuses $statuses): JsonResponse
    {
        $this->authorize('view', $run);

        $query = TransportRunPassenger::query()
            ->where('vehicle_run_id', $run->id)
            ->with('seasonPilgrim.pilgrim');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, PassengerStatus::values(), true)) {
            $query->where('status', $status);
        }

        $passengers = $query->orderBy('id')->get();
        $statuses->annotate($passengers);

        return ApiResponse::success(data: TransportRunPassengerResource::collection($passengers)->resolve());
    }

    public function addPassengers(AddPassengersRequest $request, TransportVehicleRun $run, AddPassengers $action): JsonResponse
    {
        $this->authorize('manageManifest', $run);

        $sourceId = $request->validated('source_id');
        $result = $action->execute(
            $request->user(),
            $run,
            AssignmentSource::from((string) $request->validated('source')),
            array_map('intval', (array) $request->validated('season_pilgrim_ids', [])),
            $sourceId !== null ? (int) $sourceId : null,
            (bool) $request->validated('entitlement_override', false),
            $request->validated('override_reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new TransportVehicleRunResource(RunLoader::load($result['run'])))->resolve(),
            message: 'تمت إضافة الركاب',
            meta: ['added' => $result['added']],
            status: 201,
        );
    }

    public function board(PassengerOutcomeRequest $request, TransportVehicleRun $run, RecordPassengerOutcome $action): JsonResponse
    {
        return $this->outcome($request, $run, $action, RecordPassengerOutcome::BOARD, 'تم تسجيل صعود الركاب');
    }

    public function noShow(PassengerOutcomeRequest $request, TransportVehicleRun $run, RecordPassengerOutcome $action): JsonResponse
    {
        return $this->outcome($request, $run, $action, RecordPassengerOutcome::NO_SHOW, 'تم تسجيل غياب الركاب');
    }

    public function arrivePassengers(PassengerOutcomeRequest $request, TransportVehicleRun $run, RecordPassengerOutcome $action): JsonResponse
    {
        return $this->outcome($request, $run, $action, RecordPassengerOutcome::ARRIVE, 'تم تسجيل وصول الركاب');
    }

    private function transition(OperationalFactRequest $request, TransportVehicleRun $run, TransitionRun $action, string $transition, string $message): JsonResponse
    {
        $this->authorize('operate', $run);

        $updated = $action->execute($request->user(), $run, $transition, $request->validated('occurred_at'), $request->validated('reason'), $request);

        return $this->runResponse($updated, $message);
    }

    private function outcome(PassengerOutcomeRequest $request, TransportVehicleRun $run, RecordPassengerOutcome $action, string $outcome, string $message): JsonResponse
    {
        $this->authorize('operate', $run);

        $updated = $action->execute(
            $request->user(),
            $run,
            $outcome,
            array_map('intval', (array) $request->validated('passenger_ids')),
            $request->validated('occurred_at'),
            $request->validated('reason'),
            $request,
        );

        return $this->runResponse($updated, $message);
    }

    private function runResponse(TransportVehicleRun $run, string $message = ''): JsonResponse
    {
        return ApiResponse::success(data: (new TransportVehicleRunResource(RunLoader::load($run)))->resolve(), message: $message);
    }
}
