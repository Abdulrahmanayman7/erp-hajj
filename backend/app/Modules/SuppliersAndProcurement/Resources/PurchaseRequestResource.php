<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseRequest */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PurchaseRequestStatus
            ? $this->status
            : PurchaseRequestStatus::from((string) $this->status);

        $payload = [
            'id' => $this->id,
            'pr_number' => $this->pr_number,
            'version_no' => $this->version_no,
            'status' => $status->value,
            'pending_approval_engine' => $status === PurchaseRequestStatus::Submitted,
            'request_kind' => $this->request_kind->value,
            'season_id' => $this->season_id,
            'title' => $this->title,
            'purpose' => $this->purpose,
            'priority' => $this->priority->value,
            'urgency_justification' => $this->urgency_justification,
            'priority_verified_at' => $this->priority_verified_at?->toIso8601String(),
            'priority_verified_by' => $this->userRef('priorityVerifier', $this->priority_verified_by),
            'requested_by' => $this->userRef('requester', $this->requested_by),
            'beneficiary_organization_unit_id' => $this->beneficiary_organization_unit_id,
            'optional_supplier_id' => $this->optional_supplier_id,
            'currency' => $this->currency,
            'estimated_total_priced' => (string) $this->estimated_total_priced,
            'has_unpriced_lines' => (bool) $this->has_unpriced_lines,
            'matrix_id' => $this->matrix_id,
            'matrix_version_no' => $this->matrix_version_no,
            'routing_blocked_at' => $this->routing_blocked_at?->toIso8601String(),
            'routing_block_code' => $this->routing_block_code,
            'routing_block_message' => $this->routing_block_message,
            'duplicate_warning_acknowledged_at' => $this->duplicate_warning_acknowledged_at?->toIso8601String(),
            'procurement_engaged_at' => $this->procurement_engaged_at?->toIso8601String(),
            'procurement_engagement_ref' => $this->procurement_engagement_ref,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->userRef('canceller', $this->cancelled_by),
            'cancellation_reason' => $this->cancellation_reason,
        ];

        if ($this->relationLoaded('lines')) {
            $payload['lines'] = $this->lines->map(
                fn (PurchaseRequestLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'description' => $line->description,
                    'specifications' => $line->specifications,
                    'quantity' => (string) $line->quantity,
                    'unit' => $line->unit,
                    'need_date' => $line->need_date?->format('Y-m-d'),
                    'inventory_item_id' => $line->inventory_item_id,
                    'unit_estimate' => $line->unit_estimate !== null ? (string) $line->unit_estimate : null,
                    'line_estimate' => $line->line_estimate !== null ? (string) $line->line_estimate : null,
                    'unpriced_reason' => $line->unpriced_reason,
                ],
            )->all();
        }

        if ($this->relationLoaded('financialReviews') && $this->financialReviews->isNotEmpty()) {
            /** @var PurchaseRequestFinancialReview $latest */
            $latest = $this->financialReviews->first();
            $payload['latest_financial_review'] = [
                'id' => $latest->id,
                'version_no' => $latest->version_no,
                'outcome' => $latest->outcome->value,
                'justification' => $latest->justification,
                'reviewer_id' => $latest->reviewer_id,
                'reviewed_at' => $latest->reviewed_at?->toIso8601String(),
            ];
        }

        if ($this->relationLoaded('approvalInstances')) {
            $payload['approval_instances'] = $this->approvalInstances->map(
                function (PurchaseRequestApprovalInstance $instance): array {
                    $row = [
                        'id' => $instance->id,
                        'version_no' => (int) $instance->version_no,
                        'matrix_id' => $instance->matrix_id,
                        'matrix_version_no' => (int) $instance->matrix_version_no,
                        'rule_id' => $instance->rule_id,
                        'status' => $instance->status instanceof \BackedEnum
                            ? $instance->status->value
                            : (string) $instance->status,
                        'routed_at' => $instance->routed_at?->toIso8601String(),
                    ];

                    if ($instance->relationLoaded('steps')) {
                        $row['steps'] = $instance->steps->map(
                            function (PurchaseRequestApprovalStep $step): array {
                                return [
                                    'id' => $step->id,
                                    'level_order' => (int) $step->level_order,
                                    'status' => $step->status instanceof \BackedEnum
                                        ? $step->status->value
                                        : (string) $step->status,
                                    'due_at' => $step->due_at?->toIso8601String(),
                                    'escalated_at' => $step->escalated_at?->toIso8601String(),
                                    'acted_by' => $step->acted_by !== null ? (int) $step->acted_by : null,
                                    'acted_at' => $step->acted_at?->toIso8601String(),
                                    'comment' => $step->comment,
                                    'decision_reason' => $step->decision_reason,
                                ];
                            },
                        )->values()->all();
                    }

                    return $row;
                },
            )->values()->all();
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
