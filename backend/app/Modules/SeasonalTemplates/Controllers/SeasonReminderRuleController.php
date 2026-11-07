<?php

namespace App\Modules\SeasonalTemplates\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonalTemplates\Actions\ActivateSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\ArchiveSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\CreateSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\ListSeasonReminderRules;
use App\Modules\SeasonalTemplates\Actions\PauseSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\ShowSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\UpdateSeasonReminderRule;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Requests\StoreSeasonReminderRuleRequest;
use App\Modules\SeasonalTemplates\Requests\UpdateSeasonReminderRuleRequest;
use App\Modules\SeasonalTemplates\Resources\SeasonReminderRuleResource;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reminder rules are season-scoped but addressed tenant-level: the season is a route
 * parameter resolved through the tenant scope (another tenant's id is a 404).
 */
class SeasonReminderRuleController
{
    use AuthorizesRequests;

    public function index(Request $request, Season $season, ListSeasonReminderRules $action): JsonResponse
    {
        $this->authorize('viewAny', SeasonReminderRule::class);

        $paginator = $action->execute($season, [
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: SeasonReminderRuleResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreSeasonReminderRuleRequest $request, Season $season, CreateSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('create', SeasonReminderRule::class);

        $rule = $action->execute($request->user(), $season, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($rule))->resolve(),
            message: 'تم إنشاء قاعدة التذكير بنجاح',
            status: 201,
        );
    }

    public function show(SeasonReminderRule $rule, ShowSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('view', $rule);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($action->execute($rule)))->resolve(),
        );
    }

    public function update(UpdateSeasonReminderRuleRequest $request, SeasonReminderRule $rule, UpdateSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('update', $rule);

        $rule = $action->execute($request->user(), $rule, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($rule))->resolve(),
            message: 'تم تحديث قاعدة التذكير بنجاح',
        );
    }

    public function activate(Request $request, SeasonReminderRule $rule, ActivateSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('activate', $rule);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($action->execute($request->user(), $rule, $request)))->resolve(),
            message: 'تم تفعيل قاعدة التذكير',
        );
    }

    public function pause(Request $request, SeasonReminderRule $rule, PauseSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('pause', $rule);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($action->execute($request->user(), $rule, $request)))->resolve(),
            message: 'تم إيقاف قاعدة التذكير مؤقتاً',
        );
    }

    public function archive(Request $request, SeasonReminderRule $rule, ArchiveSeasonReminderRule $action): JsonResponse
    {
        $this->authorize('archive', $rule);

        return ApiResponse::success(
            data: (new SeasonReminderRuleResource($action->execute($request->user(), $rule, $request)))->resolve(),
            message: 'تمت أرشفة قاعدة التذكير',
        );
    }
}
