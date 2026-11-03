<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CancelProcurementRfq;
use App\Modules\SuppliersAndProcurement\Actions\CreateProcurementRfq;
use App\Modules\SuppliersAndProcurement\Actions\InviteSupplierToProcurementRfq;
use App\Modules\SuppliersAndProcurement\Actions\ListProcurementRfqInvitations;
use App\Modules\SuppliersAndProcurement\Actions\ListProcurementRfqs;
use App\Modules\SuppliersAndProcurement\Actions\PublishProcurementRfq;
use App\Modules\SuppliersAndProcurement\Actions\ReplaceProcurementRfqLines;
use App\Modules\SuppliersAndProcurement\Actions\SelectProcurementRfqSupplier;
use App\Modules\SuppliersAndProcurement\Actions\UpdateProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Requests\CancelProcurementRfqRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreateProcurementRfqRequest;
use App\Modules\SuppliersAndProcurement\Requests\InviteSupplierToProcurementRfqRequest;
use App\Modules\SuppliersAndProcurement\Requests\ReplaceProcurementRfqLinesRequest;
use App\Modules\SuppliersAndProcurement\Requests\SelectProcurementRfqSupplierRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateProcurementRfqRequest;
use App\Modules\SuppliersAndProcurement\Resources\ProcurementRfqInvitationResource;
use App\Modules\SuppliersAndProcurement\Resources\ProcurementRfqResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementRfqController
{
    use AuthorizesRequests;

    public function index(Request $request, ListProcurementRfqs $action): JsonResponse
    {
        $this->authorize('viewAny', ProcurementRfq::class);

        $paginator = $action->execute($request->user(), [
            'status' => $request->query('status'),
            'purchase_request_id' => $request->query('purchase_request_id'),
            'season_id' => $request->query('season_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            ProcurementRfqResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateProcurementRfqRequest $request, CreateProcurementRfq $action): JsonResponse
    {
        $this->authorize('create', ProcurementRfq::class);

        $rfq = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
            status: 201,
        );
    }

    public function show(ProcurementRfq $rfq): JsonResponse
    {
        $this->authorize('view', $rfq);

        $rfq->load([
            'lines',
            'invitations.supplier',
            'offers.lines.rfqLine',
            'offers.supplier',
            'purchaseRequest',
            'season',
            'selectedSupplier',
            'creator',
            'publisher',
            'awarder',
            'canceller',
        ]);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }

    public function update(
        UpdateProcurementRfqRequest $request,
        ProcurementRfq $rfq,
        UpdateProcurementRfq $action,
    ): JsonResponse {
        $this->authorize('update', $rfq);

        $rfq = $action->execute($request->user(), $rfq, $request->validated(), $request);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }

    public function replaceLines(
        ReplaceProcurementRfqLinesRequest $request,
        ProcurementRfq $rfq,
        ReplaceProcurementRfqLines $action,
    ): JsonResponse {
        $this->authorize('update', $rfq);

        $rfq = $action->execute(
            $request->user(),
            $rfq,
            $request->validated('lines'),
            $request,
        );

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }

    public function publish(Request $request, ProcurementRfq $rfq, PublishProcurementRfq $action): JsonResponse
    {
        $this->authorize('publish', $rfq);

        $rfq = $action->execute($request->user(), $rfq, $request);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }

    public function cancel(
        CancelProcurementRfqRequest $request,
        ProcurementRfq $rfq,
        CancelProcurementRfq $action,
    ): JsonResponse {
        $this->authorize('cancel', $rfq);

        $rfq = $action->execute($request->user(), $rfq, $request->validated(), $request);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }

    public function invitations(ProcurementRfq $rfq, ListProcurementRfqInvitations $action): JsonResponse
    {
        $this->authorize('view', $rfq);

        $invitations = $action->execute(request()->user(), $rfq);

        return ApiResponse::success(
            data: ProcurementRfqInvitationResource::collection($invitations)->resolve(),
        );
    }

    public function invite(
        InviteSupplierToProcurementRfqRequest $request,
        ProcurementRfq $rfq,
        InviteSupplierToProcurementRfq $action,
    ): JsonResponse {
        $this->authorize('invite', $rfq);

        $invitation = $action->execute(
            $request->user(),
            $rfq,
            (int) $request->validated('supplier_id'),
            $request,
        );

        return ApiResponse::success(
            data: (new ProcurementRfqInvitationResource($invitation))->resolve(),
            status: 201,
        );
    }

    public function selectSupplier(
        SelectProcurementRfqSupplierRequest $request,
        ProcurementRfq $rfq,
        SelectProcurementRfqSupplier $action,
    ): JsonResponse {
        $this->authorize('selectSupplier', $rfq);

        $rfq = $action->execute($request->user(), $rfq, $request->validated(), $request);

        return ApiResponse::success(
            data: (new ProcurementRfqResource($rfq))->resolve(),
        );
    }
}
