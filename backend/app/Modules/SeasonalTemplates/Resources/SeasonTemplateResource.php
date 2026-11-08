<?php

namespace App\Modules\SeasonalTemplates\Resources;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Models\SeasonTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonTemplate
 */
class SeasonTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonTemplate $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'template_number' => $template->template_number,
            'name' => $template->name,
            'description' => $template->description,
            'status' => $template->status->value,
            'matrix_version' => $template->matrix_version,
            'items' => $template->relationLoaded('items')
                ? $template->items->map(static fn (SeasonTemplateItem $item): array => [
                    'entity_kind' => $item->entity_kind,
                    'sort_order' => $item->sort_order,
                    'selection' => $item->selection_json,
                    'has_snapshot' => ($item->snapshot_json['rows'] ?? []) !== [],
                    'snapshot_row_count' => count($item->snapshot_json['rows'] ?? []),
                    'snapshot_anchor' => $item->snapshot_json['anchor'] ?? null,
                    'snapshot_codes' => array_map('strval', array_keys($item->snapshot_json['rows'] ?? [])),
                ])->values()->all()
                : [],
            'approved_at' => $template->approved_at?->toIso8601String(),
            'archived_at' => $template->archived_at?->toIso8601String(),
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'created_by' => $this->userPayload($template->relationLoaded('createdBy') ? $template->createdBy : null),
            'approved_by' => $this->userPayload($template->relationLoaded('approvedBy') ? $template->approvedBy : null),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userPayload(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
