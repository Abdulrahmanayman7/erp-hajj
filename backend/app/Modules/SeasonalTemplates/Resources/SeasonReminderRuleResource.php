<?php

namespace App\Modules\SeasonalTemplates\Resources;

use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonReminderRule
 */
class SeasonReminderRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonReminderRule $rule */
        $rule = $this->resource;

        return [
            'id' => $rule->id,
            'season_id' => $rule->season_id,
            'season' => $rule->relationLoaded('season') && $rule->season !== null
                ? ['id' => $rule->season->id, 'code' => $rule->season->code, 'name' => $rule->season->name, 'status' => $rule->season->status->value]
                : null,
            'code' => $rule->code,
            'title' => $rule->title,
            'body' => $rule->body,
            'severity' => $rule->severity->value,
            'status' => $rule->status->value,
            'trigger_mode' => $rule->trigger_mode->value,
            'trigger_on' => $rule->trigger_on?->toDateString(),
            'offset_days' => $rule->offset_days,
            'recipient_mode' => $rule->recipient_mode,
            'recipient_permission' => $rule->recipient_permission,
            'created_by' => $rule->relationLoaded('createdBy') && $rule->createdBy !== null
                ? ['id' => $rule->createdBy->id, 'name' => $rule->createdBy->name]
                : null,
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
