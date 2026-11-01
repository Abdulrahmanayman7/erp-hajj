<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierEvaluationAppeal */
class SupplierEvaluationAppealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'evaluation_id' => $this->evaluation_id,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'filed_by' => $this->userRef('filer', $this->filed_by),
            'filed_at' => $this->filed_at?->toIso8601String(),
            'reviewed_by' => $this->userRef('reviewer', $this->reviewed_by),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_outcome_note' => $this->review_outcome_note,
            'resulting_evaluation_id' => $this->resulting_evaluation_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('resultingEvaluation') && $this->resultingEvaluation) {
            $payload['resulting_evaluation'] = (new SupplierEvaluationResource($this->resultingEvaluation))->resolve();
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
