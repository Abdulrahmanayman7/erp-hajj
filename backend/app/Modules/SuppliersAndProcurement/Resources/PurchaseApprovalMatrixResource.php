<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseApprovalMatrix */
class PurchaseApprovalMatrixResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'version_no' => $this->version_no,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'published_by' => $this->userRef('publisher', $this->published_by),
            'created_by' => $this->userRef('creator', $this->created_by),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('rules')) {
            $payload['rules'] = $this->rules->map(
                fn (PurchaseApprovalMatrixRule $rule): array => [
                    'id' => $rule->id,
                    'min_amount' => $rule->min_amount !== null ? (string) $rule->min_amount : null,
                    'max_amount' => $rule->max_amount !== null ? (string) $rule->max_amount : null,
                    'request_kind' => $rule->request_kind?->value,
                    'organization_unit_id' => $rule->organization_unit_id,
                    'season_id' => $rule->season_id,
                    'applies_when_unpriced' => (bool) $rule->applies_when_unpriced,
                    'specificity_hint' => $rule->specificity_hint,
                ],
            )->all();
        }

        if ($this->relationLoaded('levels')) {
            $payload['levels'] = $this->levels->map(
                fn (PurchaseApprovalMatrixLevel $level): array => [
                    'id' => $level->id,
                    'level_order' => $level->level_order,
                    'name' => $level->name,
                ],
            )->all();
        }

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
