<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierEvaluationCriteriaPack */
class EvaluationCriteriaPackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'supplier_category_id' => $this->supplier_category_id,
            'version_no' => $this->version_no,
            'status' => $this->status->value,
            'prepared_by' => $this->userRef('preparer', $this->prepared_by),
            'last_edited_by' => $this->userRef('lastEditor', $this->last_edited_by),
            'approved_by' => $this->userRef('approver', $this->approved_by),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'supersedes_pack_id' => $this->supersedes_pack_id,
            'notes' => $this->notes,
        ];

        if ($this->relationLoaded('criteria')) {
            $payload['criteria'] = $this->criteria->map(
                fn (SupplierEvaluationCriterion $line): array => [
                    'id' => $line->id,
                    'name' => $line->name,
                    'weight' => (string) $line->weight,
                    'sort_order' => $line->sort_order,
                    'is_active' => $line->is_active,
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
