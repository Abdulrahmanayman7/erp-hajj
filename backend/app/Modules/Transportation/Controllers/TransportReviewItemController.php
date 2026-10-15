<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\Transportation\Actions\Reviews\ResolveReviewItem;
use App\Modules\Transportation\Enums\ReviewSeverity;
use App\Modules\Transportation\Enums\ReviewStatus;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Requests\ResolveReviewRequest;
use App\Modules\Transportation\Resources\TransportReviewItemResource;
use App\Modules\Transportation\Support\PaginationMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class TransportReviewItemController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportReviewItem::class);

        $query = TransportReviewItem::query()->where('season_id', $this->context->require()->id);

        $status = $request->query('status');
        if (is_string($status) && in_array($status, ReviewStatus::values(), true)) {
            $query->where('status', $status);
        }
        $severity = $request->query('severity');
        if (is_string($severity) && in_array($severity, ReviewSeverity::values(), true)) {
            $query->where('severity', $severity);
        }

        $paginator = $query->orderByDesc('id')->paginate(PaginationMeta::perPage($request->query('per_page')));

        return ApiResponse::success(
            data: TransportReviewItemResource::collection($paginator->items())->resolve(),
            meta: PaginationMeta::from($paginator),
        );
    }

    public function resolve(ResolveReviewRequest $request, TransportReviewItem $reviewItem, ResolveReviewItem $action): JsonResponse
    {
        $this->authorize('resolve', $reviewItem);

        $item = $action->execute($request->user(), $reviewItem, (string) $request->validated('resolution_note'), $request);

        return ApiResponse::success(data: (new TransportReviewItemResource($item))->resolve(), message: 'تم إغلاق بند المراجعة');
    }
}
