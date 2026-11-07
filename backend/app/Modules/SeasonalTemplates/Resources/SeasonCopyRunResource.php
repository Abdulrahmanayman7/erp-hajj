<?php

namespace App\Modules\SeasonalTemplates\Resources;

use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRunLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonCopyRun
 */
class SeasonCopyRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonCopyRun $run */
        $run = $this->resource;

        $payload = [
            'id' => $run->id,
            'run_number' => $run->run_number,
            'preview_id' => $run->preview_id,
            'mode' => $run->mode->value,
            'source_season_id' => $run->source_season_id,
            'source_template_id' => $run->source_template_id,
            'target_season_id' => $run->target_season_id,
            'status' => $run->status->value,
            'error_code' => $run->error_code,
            'error_message' => $run->error_message,
            'counts' => $run->counts_json,
            'correlation_id' => $run->correlation_id,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_by' => $run->created_by,
        ];

        if ($run->relationLoaded('lines')) {
            $payload['lines'] = $run->lines->map(static fn (SeasonCopyRunLine $line): array => [
                'entity_kind' => $line->entity_kind,
                'natural_key' => $line->natural_key,
                'result' => $line->result,
                'reason_code' => $line->reason_code,
                'source_row_id' => $line->source_row_id,
                'target_row_id' => $line->target_row_id,
            ])->values()->all();
        }

        return $payload;
    }
}
