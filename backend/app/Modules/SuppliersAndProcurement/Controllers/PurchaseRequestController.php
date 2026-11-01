<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\ApprovePurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\CancelApprovedPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\CancelPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\ListPurchaseRequestApprovalInbox;
use App\Modules\SuppliersAndProcurement\Actions\ListPurchaseRequests;
use App\Modules\SuppliersAndProcurement\Actions\RecordPurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Actions\RejectPurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\ReplacePurchaseRequestLines;
use App\Modules\SuppliersAndProcurement\Actions\ReturnPurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\RoutePurchaseRequestApproval;
use App\Modules\SuppliersAndProcurement\Actions\SubmitPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\VerifyPurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestVersion;
use App\Modules\SuppliersAndProcurement\Requests\ApprovePurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\CancelApprovedPurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\CancelPurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreatePurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\RecordPurchaseRequestFinancialReviewRequest;
use App\Modules\SuppliersAndProcurement\Requests\RejectPurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\ReplacePurchaseRequestLinesRequest;
use App\Modules\SuppliersAndProcurement\Requests\ReturnPurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\SubmitPurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePurchaseRequestRequest;
use App\Modules\SuppliersAndProcurement\Resources\PurchaseRequestResource;
use App\Modules\SuppliersAndProcurement\Support\FindPurchaseRequestDuplicateHints;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController
{
    use AuthorizesRequests;

    public function index(Request $request, ListPurchaseRequests $action): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $paginator = $action->execute($request->user(), [
            'status' => $request->query('status'),
            'request_kind' => $request->query('request_kind'),
            'season_id' => $request->query('season_id'),
            'priority' => $request->query('priority'),
            'optional_supplier_id' => $request->query('optional_supplier_id'),
            'beneficiary_organization_unit_id' => $request->query('beneficiary_organization_unit_id'),
            'requested_by' => $request->query('requested_by'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            PurchaseRequestResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function approvalInbox(
        Request $request,
        ListPurchaseRequestApprovalInbox $action,
    ): JsonResponse {
        $this->authorize('viewApprovalInbox', PurchaseRequest::class);

        $items = $action->execute($request->user());

        return ApiResponse::success(
            data: PurchaseRequestResource::collection($items)->resolve(),
        );
    }

    public function store(
        CreatePurchaseRequestRequest $request,
        CreatePurchaseRequest $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseRequest::class);

        $pr = $action->execute($request->user(), $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم إنشاء مسودة طلب الشراء',
            status: 201,
        );
    }

    public function show(PurchaseRequest $purchase_request): JsonResponse
    {
        $this->authorize('view', $purchase_request);

        $purchase_request->load([
            'lines',
            'requester',
            'canceller',
            'priorityVerifier',
            'financialReviews',
            'beneficiaryUnit',
            'optionalSupplier',
            'season',
            'approvalInstances.steps',
        ]);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($purchase_request))->resolve(),
        );
    }

    public function update(
        UpdatePurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        UpdatePurchaseRequest $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم تحديث طلب الشراء',
        );
    }

    public function replaceLines(
        ReplacePurchaseRequestLinesRequest $request,
        PurchaseRequest $purchase_request,
        ReplacePurchaseRequestLines $action,
    ): JsonResponse {
        $this->authorize('update', $purchase_request);

        $pr = $action->execute(
            $request->user(),
            $purchase_request,
            $request->validated()['lines'],
            $request,
        );
        $pr->load(['lines', 'requester', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم تحديث بنود طلب الشراء',
        );
    }

    public function submit(
        SubmitPurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        SubmitPurchaseRequest $action,
    ): JsonResponse {
        $this->authorize('submit', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم إرسال طلب الشراء — بانتظار محرك الاعتماد',
        );
    }

    public function route(
        Request $request,
        PurchaseRequest $purchase_request,
        RoutePurchaseRequestApproval $action,
    ): JsonResponse {
        $this->authorize('route', $purchase_request);

        $pr = $action->execute(
            (int) $purchase_request->id,
            (int) $purchase_request->version_no,
            $request->user(),
            $request,
        );
        $pr->load(['lines', 'requester', 'financialReviews', 'approvalInstances']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: $pr->status->value === 'under_review'
                ? 'تم توجيه طلب الشراء لمسار الاعتماد'
                : 'اكتملت محاولة التوجيه — راجع حالة الحظر إن وُجدت',
        );
    }

    public function approve(
        ApprovePurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        ApprovePurchaseRequestStep $action,
    ): JsonResponse {
        $this->authorize('approve', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews', 'approvalInstances.steps']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: $pr->status->value === 'approved'
                ? 'تم اعتماد طلب الشراء نهائياً'
                : 'تم اعتماد خطوة الاعتماد الحالية',
        );
    }

    public function reject(
        RejectPurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        RejectPurchaseRequestStep $action,
    ): JsonResponse {
        $this->authorize('reject', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews', 'approvalInstances.steps']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم رفض طلب الشراء',
        );
    }

    public function returnForRevision(
        ReturnPurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        ReturnPurchaseRequestStep $action,
    ): JsonResponse {
        $this->authorize('return', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'financialReviews', 'approvalInstances.steps']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'أُعيد طلب الشراء للتعديل',
        );
    }

    public function cancel(
        CancelPurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        CancelPurchaseRequest $action,
    ): JsonResponse {
        $this->authorize('cancel', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'canceller', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم إلغاء طلب الشراء',
        );
    }

    public function cancelApproved(
        CancelApprovedPurchaseRequestRequest $request,
        PurchaseRequest $purchase_request,
        CancelApprovedPurchaseRequest $action,
    ): JsonResponse {
        $this->authorize('cancelApproved', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request->validated(), $request);
        $pr->load(['lines', 'requester', 'canceller', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم إلغاء طلب الشراء المعتمد',
        );
    }

    public function verifyPriority(
        Request $request,
        PurchaseRequest $purchase_request,
        VerifyPurchaseRequestPriority $action,
    ): JsonResponse {
        $this->authorize('verifyPriority', $purchase_request);

        $pr = $action->execute($request->user(), $purchase_request, $request);
        $pr->load(['lines', 'requester', 'priorityVerifier', 'financialReviews']);

        return ApiResponse::success(
            data: (new PurchaseRequestResource($pr))->resolve(),
            message: 'تم التحقق من أولوية طلب الشراء',
        );
    }

    public function storeFinancialReview(
        RecordPurchaseRequestFinancialReviewRequest $request,
        PurchaseRequest $purchase_request,
        RecordPurchaseRequestFinancialReview $action,
    ): JsonResponse {
        $this->authorize('financialReview', $purchase_request);

        $data = $request->validated();
        $data['version_no'] = (int) ($data['version_no'] ?? $purchase_request->version_no);

        $review = $action->execute($request->user(), $purchase_request, $data, $request);

        return ApiResponse::success(
            data: [
                'id' => $review->id,
                'purchase_request_id' => $review->purchase_request_id,
                'version_no' => $review->version_no,
                'outcome' => $review->outcome->value,
                'justification' => $review->justification,
                'reviewer_id' => $review->reviewer_id,
                'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            ],
            message: 'تم تسجيل المراجعة المالية',
            status: 201,
        );
    }

    public function indexFinancialReviews(PurchaseRequest $purchase_request): JsonResponse
    {
        $this->authorize('view', $purchase_request);

        $rows = PurchaseRequestFinancialReview::query()
            ->where('purchase_request_id', $purchase_request->id)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseRequestFinancialReview $r): array => [
                'id' => $r->id,
                'version_no' => $r->version_no,
                'outcome' => $r->outcome->value,
                'justification' => $r->justification,
                'reviewer_id' => $r->reviewer_id,
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            ])
            ->all();

        return ApiResponse::success(data: $rows);
    }

    public function indexVersions(Request $request, PurchaseRequest $purchase_request): JsonResponse
    {
        $this->authorize('view', $purchase_request);

        $includeLines = $request->query('include') === 'lines';
        $query = PurchaseRequestVersion::query()
            ->where('purchase_request_id', $purchase_request->id)
            ->orderByDesc('version_no');

        if ($includeLines) {
            $query->with('lines');
        }

        $rows = $query->get()->map(function (PurchaseRequestVersion $v) use ($includeLines): array {
            $row = [
                'id' => $v->id,
                'version_no' => $v->version_no,
                'status' => $v->status instanceof \BackedEnum ? $v->status->value : $v->status,
                'request_kind' => $v->request_kind instanceof \BackedEnum ? $v->request_kind->value : $v->request_kind,
                'season_id' => $v->season_id,
                'title' => $v->title,
                'purpose' => $v->purpose,
                'priority' => $v->priority instanceof \BackedEnum ? $v->priority->value : $v->priority,
                'estimated_total_priced' => (string) $v->estimated_total_priced,
                'has_unpriced_lines' => (bool) $v->has_unpriced_lines,
                'snapshot_at' => $v->snapshot_at?->toIso8601String(),
                'submitted_by' => $v->submitted_by,
            ];
            if ($includeLines && $v->relationLoaded('lines')) {
                $row['lines'] = $v->lines->map(fn ($l) => [
                    'line_no' => $l->line_no,
                    'description' => $l->description,
                    'quantity' => (string) $l->quantity,
                    'unit' => $l->unit,
                    'need_date' => $l->need_date?->format('Y-m-d'),
                    'unit_estimate' => $l->unit_estimate !== null ? (string) $l->unit_estimate : null,
                    'line_estimate' => $l->line_estimate !== null ? (string) $l->line_estimate : null,
                    'unpriced_reason' => $l->unpriced_reason,
                ])->all();
            }

            return $row;
        })->all();

        return ApiResponse::success(data: $rows);
    }

    public function duplicateHints(
        Request $request,
        FindPurchaseRequestDuplicateHints $hints,
    ): JsonResponse {
        $this->authorize('viewAny', PurchaseRequest::class);

        if ($request->filled('purchase_request_id')) {
            $pr = PurchaseRequest::query()->whereKey((int) $request->query('purchase_request_id'))->firstOrFail();
            $this->authorize('view', $pr);

            return ApiResponse::success(data: $hints->forPurchaseRequest($pr));
        }

        $lines = $request->input('lines');
        if (is_string($lines)) {
            $decoded = json_decode($lines, true);
            $lines = is_array($decoded) ? $decoded : null;
        }

        if (is_array($lines) && $lines !== []) {
            return ApiResponse::success(data: $hints->forDraftPayload([
                'season_id' => $request->input('season_id'),
                'lines' => $lines,
            ]));
        }

        return ApiResponse::success(data: []);
    }
}
