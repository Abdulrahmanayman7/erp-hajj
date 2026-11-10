<?php

namespace App\Modules\SeasonProjects\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonProjects\Actions\ApproveSeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Actions\UpsertSeasonProjectProgressBaselineDraft;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
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

    public function storeDraft(Request $request, SeasonProject $season_project, UpsertSeasonProjectProgressBaselineDraft $action): JsonResponse
    {
        $checkpoints = $request->input('checkpoints');
        if (! is_array($checkpoints) || $checkpoints === []) {
            throw new UnprocessableEntityHttpException('Invalid checkpoints.');
        }

        foreach ($checkpoints as $row) {
            if (! is_array($row) || ! isset($row['effective_at'], $row['planned_progress_percent'])) {
                throw new UnprocessableEntityHttpException('Invalid checkpoints.');
            }
            $pct = (float) $row['planned_progress_percent'];
            if ($pct < 0 || $pct > 100) {
                throw new UnprocessableEntityHttpException('Invalid planned_progress_percent.');
            }
        }

        $baseline = $action->execute($request->user(), $season_project, $checkpoints);

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
