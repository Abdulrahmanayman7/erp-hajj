<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'supplier_number' => $this->supplier_number,
            'status' => $this->status->value,
            'supplier_type' => $this->supplier_type->value,
            'display_name' => $this->display_name,
            'legal_name' => $this->legal_name,
            'country_code' => $this->country_code,
            'legal_id_type' => $this->legal_id_type,
            'legal_id_value' => $this->legal_id_value,
            'notes' => $this->notes,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'categories' => SupplierCategoryResource::collection($this->whenLoaded('categories'))->resolve(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('statusTransitions')) {
            $payload['transitions'] = $this->statusTransitions->map(
                fn (SupplierStatusTransition $t): array => [
                    'id' => $t->id,
                    'from_status' => $t->from_status,
                    'to_status' => $t->to_status,
                    'reason' => $t->reason,
                    'actor' => $t->relationLoaded('actor') && $t->actor !== null
                        ? ['id' => $t->actor->id, 'name' => $t->actor->name]
                        : null,
                    'created_at' => $t->created_at?->toIso8601String(),
                ],
            )->all();
        }

        if ($this->relationLoaded('contacts')) {
            $payload['contacts'] = SupplierContactResource::collection($this->contacts)->resolve();
        }

        if ($this->relationLoaded('coverages')) {
            $payload['coverages'] = SupplierCoverageResource::collection($this->coverages)->resolve();
        }

        return $payload;
    }
}
