<?php

namespace App\Modules\SeasonalTemplates\Resources;

use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreviewLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lines carry allowlisted, non-PII field summaries only.
 *
 * @mixin SeasonCopyPreview
 */
class SeasonCopyPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonCopyPreview $preview */
        $preview = $this->resource;

        $lines = $preview->relationLoaded('lines')
            ? $preview->lines->map(static fn (SeasonCopyPreviewLine $line): array => [
                'entity_kind' => $line->entity_kind,
                'action' => $line->action,
                'natural_key' => $line->natural_key,
                'conflict_code' => $line->conflict_code,
                'proposed_resolution' => $line->proposed_resolution,
                'summary' => $line->summary_json ?? [],
            ])->values()
            : collect();

        return [
            'id' => $preview->id,
            'preview_token' => $preview->preview_token,
            'mode' => $preview->mode->value,
            'source_season_id' => $preview->source_season_id,
            'source_template_id' => $preview->source_template_id,
            'target_season_id' => $preview->target_season_id,
            'matrix_version' => $preview->matrix_version,
            'content_hash' => $preview->content_hash,
            'status' => $preview->status->value,
            'expires_at' => $preview->expires_at->toIso8601String(),
            'selection' => $preview->selection_json,
            'date_mapping' => $preview->date_mapping_json,
            'counts' => $preview->counts_json,
            'lines' => $lines->all(),
            'exclusions' => $lines
                ->where('action', 'exclude')
                ->map(static fn (array $line): array => [
                    'entity_kind' => $line['entity_kind'],
                    'natural_key' => $line['natural_key'],
                    'reason_code' => $line['summary']['reason_code'] ?? null,
                ])
                ->values()
                ->all(),
            'created_at' => $preview->created_at?->toIso8601String(),
        ];
    }
}
