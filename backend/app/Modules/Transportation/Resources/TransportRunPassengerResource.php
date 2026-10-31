<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportRunPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Manifest row. Exposes no pilgrim identity numbers. `entitlement_status`
 * is the live status; `assignment_context` is the immutable T9 snapshot.
 *
 * @mixin TransportRunPassenger
 */
class TransportRunPassengerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportRunPassenger $passenger */
        $passenger = $this->resource;
        $seasonPilgrim = $passenger->relationLoaded('seasonPilgrim') ? $passenger->seasonPilgrim : null;

        return [
            'id' => $passenger->id,
            'season_id' => $passenger->season_id,
            'vehicle_run_id' => $passenger->vehicle_run_id,
            'season_pilgrim_id' => $passenger->season_pilgrim_id,
            'season_pilgrim' => $seasonPilgrim === null ? null : [
                'id' => $seasonPilgrim->id,
                'reference_number' => $seasonPilgrim->reference_number,
                'status' => $seasonPilgrim->status->value,
                'full_name' => $seasonPilgrim->relationLoaded('pilgrim') ? $seasonPilgrim->pilgrim?->full_name : null,
                'season_program_id' => $seasonPilgrim->season_program_id,
            ],
            'status' => $passenger->status->value,
            'assignment_source' => $passenger->assignment_source->value,
            'source_group_id' => $passenger->source_group_id,
            'source_group_label' => $passenger->source_group_label,
            'program_id_at_assignment' => $passenger->program_id_at_assignment,
            'program_code_at_assignment' => $passenger->program_code_at_assignment,
            'entitlement_vehicle_class' => $passenger->entitlement_vehicle_class,
            'entitlement_service_level' => $passenger->entitlement_service_level,
            'entitlement_status_at_assignment' => $passenger->entitlement_status_at_assignment->value,
            'assignment_context' => $passenger->assignmentContext(),
            'entitlement_status' => $passenger->getAttribute('live_entitlement_status'),
            'entitlement_override' => (bool) $passenger->entitlement_override,
            'entitlement_override_reason' => $passenger->entitlement_override_reason,
            'entitlement_overridden_by' => $passenger->entitlement_overridden_by,
            'entitlement_overridden_at' => $passenger->entitlement_overridden_at?->toIso8601String(),
            'assigned_at' => $passenger->assigned_at?->toIso8601String(),
            'boarded_at' => $passenger->boarded_at?->toIso8601String(),
            'arrived_at' => $passenger->arrived_at?->toIso8601String(),
            'no_show_at' => $passenger->no_show_at?->toIso8601String(),
            'cancelled_at' => $passenger->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $passenger->cancellation_reason,
        ];
    }
}
