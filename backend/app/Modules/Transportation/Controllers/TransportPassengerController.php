<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\Passengers\CancelPassenger;
use App\Modules\Transportation\Actions\Passengers\OverridePassengerEntitlement;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Requests\TransportReasonRequest;
use App\Modules\Transportation\Resources\TransportRunPassengerResource;
use App\Modules\Transportation\Support\PassengerEntitlementStatuses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Season-scoped (`season.context`).
 */
class TransportPassengerController
{
    use AuthorizesRequests;

    public function __construct(private readonly PassengerEntitlementStatuses $statuses) {}

    public function cancel(TransportReasonRequest $request, TransportRunPassenger $passenger, CancelPassenger $action): JsonResponse
    {
        $this->authorize('cancel', $passenger);

        $updated = $action->execute($request->user(), $passenger, (string) $request->validated('reason'), $request);

        return $this->respond($updated, 'تم إلغاء تعيين الراكب');
    }

    public function overrideEntitlement(TransportReasonRequest $request, TransportRunPassenger $passenger, OverridePassengerEntitlement $action): JsonResponse
    {
        $this->authorize('overrideEntitlement', $passenger);

        $updated = $action->execute($request->user(), $passenger, (string) $request->validated('reason'), $request);

        return $this->respond($updated, 'تم تجاوز استحقاق النقل');
    }

    private function respond(TransportRunPassenger $passenger, string $message): JsonResponse
    {
        $this->statuses->annotate(collect([$passenger]));

        return ApiResponse::success(data: (new TransportRunPassengerResource($passenger))->resolve(), message: $message);
    }
}
