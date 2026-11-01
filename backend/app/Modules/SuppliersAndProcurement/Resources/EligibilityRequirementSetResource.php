<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierEligibilityRequirementSet */
class EligibilityRequirementSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'config_key' => $this->config_key,
            'name' => $this->name,
            'status' => $this->status->value,
            'version_no' => $this->version_no,
            'authored_by' => $this->userRef('author', $this->authored_by),
            'last_edited_by' => $this->userRef('lastEditor', $this->last_edited_by),
            'approved_by' => $this->userRef('approver', $this->approved_by),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('requirementDefs')) {
            $payload['requirement_defs'] = $this->requirementDefs->map(
                fn (SupplierDocumentRequirementDef $def): array => [
                    'id' => $def->id,
                    'requirement_key' => $def->requirement_key,
                    'label_ar' => $def->label_ar,
                    'description' => $def->description,
                    'supplier_type' => $def->supplier_type->value,
                    'country_code' => $def->country_code,
                    'supplier_category_id' => $def->supplier_category_id,
                    'is_mandatory' => $def->is_mandatory,
                    'default_validity_days' => $def->default_validity_days,
                ],
            )->all();
        }

        $payload['created_at'] = $this->created_at?->toIso8601String();
        $payload['updated_at'] = $this->updated_at?->toIso8601String();

        return $payload;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userRef(string $relation, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $user = $this->relationLoaded($relation) ? $this->{$relation} : null;

        return ['id' => (int) $id, 'name' => $user?->name ?? ''];
    }
}
