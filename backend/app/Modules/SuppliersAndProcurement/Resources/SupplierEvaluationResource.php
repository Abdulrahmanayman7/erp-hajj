<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationScoreSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierEvaluation */
class SupplierEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'evaluation_number' => $this->evaluation_number,
            'supplier_id' => $this->supplier_id,
            'supplier_category_id' => $this->supplier_category_id,
            'evaluation_kind' => $this->evaluation_kind->value,
            'season_id' => $this->season_id,
            'status' => $this->status->value,
            'criteria_pack_id' => $this->criteria_pack_id,
            'criteria_pack_version_no' => $this->criteria_pack_version_no,
            'total_score' => $this->total_score !== null ? (string) $this->total_score : null,
            'monitoring_recommended' => (bool) $this->monitoring_recommended,
            'prepared_by' => $this->userRef('preparer', $this->prepared_by),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_by' => $this->userRef('approver', $this->approved_by),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->userRef('rejector', $this->rejected_by),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'version_no' => $this->version_no,
            'evaluation_family_key' => $this->evaluation_family_key,
            'supersedes_evaluation_id' => $this->supersedes_evaluation_id,
            'origin_appeal_id' => $this->origin_appeal_id,
            'notes' => $this->notes,
            'draft_scores' => $this->status->value === 'draft' ? ($this->draft_scores ?? []) : null,
        ];

        if ($this->relationLoaded('scoreSnapshots')) {
            $payload['score_snapshots'] = $this->scoreSnapshots->map(
                fn (SupplierEvaluationScoreSnapshot $row): array => [
                    'id' => $row->id,
                    'source_criterion_id' => $row->source_criterion_id,
                    'criterion_name' => $row->criterion_name,
                    'weight' => (string) $row->weight,
                    'score' => (string) $row->score,
                    'note' => $row->note,
                    'sort_order' => $row->sort_order,
                ],
            )->all();
        }

        if ($this->relationLoaded('supplier') && $this->supplier) {
            $payload['supplier'] = [
                'id' => $this->supplier->id,
                'supplier_number' => $this->supplier->supplier_number,
                'display_name' => $this->supplier->display_name,
            ];
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
