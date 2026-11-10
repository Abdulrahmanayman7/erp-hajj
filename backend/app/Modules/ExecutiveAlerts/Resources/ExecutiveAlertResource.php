<?php

namespace App\Modules\ExecutiveAlerts\Resources;

use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutiveAlert
 */
class ExecutiveAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExecutiveAlert $alert */
        $alert = $this->resource;

        return [
            'id' => $alert->id,
            'season_id' => $alert->season_id,
            'rule_code' => $alert->rule_code->value,
            'status' => $alert->status->value,
            'severity' => $alert->severity->value,
            'title' => $alert->title,
            'body' => $alert->body,
            'source_entity_type' => $alert->source_entity_type,
            'source_entity_id' => $alert->source_entity_id,
            'opened_at' => $alert->opened_at->toIso8601String(),
            'last_seen_at' => $alert->last_seen_at->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $alert->acknowledged_by,
            'cleared_at' => $alert->cleared_at?->toIso8601String(),
        ];
    }
}
