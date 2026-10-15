<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportEntitlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportEntitlement
 */
class TransportEntitlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportEntitlement $entitlement */
        $entitlement = $this->resource;

        return [
            'id' => $entitlement->id,
            'season_id' => $entitlement->season_id,
            'season_program_id' => $entitlement->season_program_id,
            'program' => $this->whenLoaded('program', fn () => [
                'id' => $entitlement->program->id,
                'code' => $entitlement->program->code,
                'name' => $entitlement->program->name,
            ]),
            'vehicle_class' => $entitlement->vehicle_class?->value,
            'service_level' => $entitlement->service_level?->value,
            'notes' => $entitlement->notes,
            'created_at' => $entitlement->created_at?->toIso8601String(),
            'updated_at' => $entitlement->updated_at?->toIso8601String(),
        ];
    }
}
