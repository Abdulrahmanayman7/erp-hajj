<?php

namespace App\Modules\SeasonProjects\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonProjects\Actions\ApproveSeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Actions\UpsertSeasonProjectProgressBaselineDraft;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Requests\UpsertSeasonProjectProgressBaselineDraftRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SeasonProjectProgressBaselineController
{
    use AuthorizesRequests;

    public function index(SeasonProject $season_project): JsonResponse
    {
        $this->authorize('view', $season_project);

        $rows = SeasonProjectProgressBaseline::query()
            ->where('season_project_id', $season_project->id)
            ->with('checkpoints')
            ->orderByDesc('version')
            ->get()
            ->map(static fn (SeasonProjectProgressBaseline $b): array => [
                'id' => $b->id,
                'version' => $b->version,
                'status' => $b->status->value,
                'approved_at' => $b->approved_at?->toIso8601String(),
                'approved_by' => $b->approved_by,
                'checkpoints' => $b->checkpoints->map(static fn ($c): array => [
                    'id' => $c->id,
                    'effective_at' => $c->effective_at->toIso8601String(),
                    'planned_progress_percent' => (float) $c->planned_progress_percent,
                ])->values()->all(),
            ])
            ->all();

        return ApiResponse::success(data: $rows);
    }

    public function storeDraft(
        UpsertSeasonProjectProgressBaselineDraftRequest $request,
        SeasonProject $season_project,
        UpsertSeasonProjectProgressBaselineDraft $action,
    ): JsonResponse {
        /** @var list<array{effective_at: string, planned_progress_percent: float|int|string}> $checkpoints */
        $checkpoints = array_values($request->validated('checkpoints'));

        $baseline = $action->execute($request->user(), $season_project, $checkpoints, $request);

        return ApiResponse::success(data: [
            'id' => $baseline->id,
            'version' => $baseline->version,
            'status' => $baseline->status->value,
        ], status: 201);
    }

    public function approve(Request $request, SeasonProject $season_project, SeasonProjectProgressBaseline $baseline, ApproveSeasonProjectProgressBaseline $action): JsonResponse
    {
        if ((int) $baseline->season_project_id !== (int) $season_project->id) {
            abort(404);
        }

        if ($baseline->status !== SeasonProjectProgressBaselineStatus::Draft) {
            throw new UnprocessableEntityHttpException('Baseline is not a draft.');
        }

        $approved = $action->execute($request->user(), $season_project, (int) $baseline->id, $request);

        return ApiResponse::success(data: [
            'id' => $approved->id,
            'version' => $approved->version,
            'status' => $approved->status->value,
            'approved_at' => $approved->approved_at?->toIso8601String(),
        ]);
    }
}
