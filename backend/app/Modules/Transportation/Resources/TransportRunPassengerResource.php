<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportRunPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Manifest row. Exposes no pilgrim identity numbers. `assignment_context`
 * is the immutable T9 decision; `entitlement_status` is the live status set
 * by PassengerEntitlementStatuses.
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
            'assignment_context' => [
                'season_program_id' => $passenger->program_id_at_assignment,
                'season_program_code' => $passenger->program_code_at_assignment,
                'entitlement_id' => $passenger->entitlement_id_at_assignment,
                'required_vehicle_class' => $passenger->required_vehicle_class_at_assignment,
                'required_service_level' => $passenger->required_service_level_at_assignment,
                'transport_season_vehicle_id' => $passenger->season_vehicle_id_at_assignment,
                'vehicle_class' => $passenger->vehicle_class_at_assignment,
                'service_level' => $passenger->service_level_at_assignment,
                'entitlement_status' => $passenger->entitlement_status_at_assignment->value,
                'override_used' => (bool) $passenger->override_at_assignment,
                'override_reason' => $passenger->override_reason_at_assignment,
                'assigned_by' => $passenger->assigned_by,
                'assigned_at' => $passenger->assigned_at?->toIso8601String(),
            ],
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
