<?php

namespace App\Modules\Decisions\Resources;

use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Models\DecisionStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Decision
 */
class DecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Decision $decision */
        $decision = $this->resource;

        $payload = [
            'id' => $decision->id,
            'decision_number' => $decision->decision_number,
            'title' => $decision->title,
            'body' => $decision->body,
            'notes' => $decision->notes,
            'status' => $decision->status->value,
            'source_recommendation_id' => $decision->source_recommendation_id,
            'organization_unit_id' => $decision->organization_unit_id,
            'issued_by_employee_id' => $decision->issued_by_employee_id,
            'responsible_employee_id' => $decision->responsible_employee_id,
            'effective_date' => $decision->effective_date?->toDateString(),
            'due_date' => $decision->due_date?->toDateString(),
            'organization_unit' => $this->organizationUnitPayload($decision),
            'issued_by_employee' => $this->employeeSummary(
                $decision->relationLoaded('issuedByEmployee') ? $decision->issuedByEmployee : null,
            ),
            'responsible_employee' => $this->employeeSummary(
                $decision->relationLoaded('responsibleEmployee') ? $decision->responsibleEmployee : null,
            ),
            'created_by' => $this->createdByPayload($decision),
            'source_recommendation' => $this->sourceRecommendationPayload($decision),
            'source_meeting' => $this->sourceMeetingPayload($decision),
            'created_at' => $decision->created_at?->toIso8601String(),
            'updated_at' => $decision->updated_at?->toIso8601String(),
        ];

        if ($decision->relationLoaded('statusTransitions')) {
            $payload['status_transitions'] = $decision->statusTransitions
                ->map(fn (DecisionStatusTransition $row): array => $this->transitionPayload($row))
                ->values()
                ->all();
        }

        if (isset($decision->tasks_summary) && is_array($decision->tasks_summary)) {
            $payload['tasks_summary'] = $decision->tasks_summary;
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function organizationUnitPayload(Decision $decision): ?array
    {
        if (! $decision->relationLoaded('organizationUnit') || $decision->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $decision->organizationUnit->id,
            'name' => $decision->organizationUnit->name,
            'code' => $decision->organizationUnit->code,
        ];
    }

    /**
     * @return array{id: int, employee_number: string, full_name: string}|null
     */
    private function employeeSummary(mixed $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Decision $decision): ?array
    {
        if (! $decision->relationLoaded('createdBy') || $decision->createdBy === null) {
            return null;
        }

        return [
            'id' => $decision->createdBy->id,
            'name' => $decision->createdBy->name,
        ];
    }

    /**
     * @return array{id: int, title: string, status: string, meeting_id: int}|null
     */
    private function sourceRecommendationPayload(Decision $decision): ?array
    {
        if (! $decision->relationLoaded('sourceRecommendation') || $decision->sourceRecommendation === null) {
            return null;
        }

        $rec = $decision->sourceRecommendation;

        return [
            'id' => $rec->id,
            'title' => $rec->title,
            'status' => $rec->status->value,
            'meeting_id' => $rec->meeting_id,
        ];
    }

    /**
     * @return array{id: int, meeting_number: string, title: string, status: string}|null
     */
    private function sourceMeetingPayload(Decision $decision): ?array
    {
        if (! $decision->relationLoaded('sourceRecommendation') || $decision->sourceRecommendation === null) {
            return null;
        }

        $rec = $decision->sourceRecommendation;
        if (! $rec->relationLoaded('meeting') || $rec->meeting === null) {
            return null;
        }

        $meeting = $rec->meeting;

        return [
            'id' => $meeting->id,
            'meeting_number' => $meeting->meeting_number,
            'title' => $meeting->title,
            'status' => $meeting->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionPayload(DecisionStatusTransition $row): array
    {
        $actor = null;
        if ($row->relationLoaded('performer') && $row->performer !== null) {
            $actor = [
                'id' => $row->performer->id,
                'name' => $row->performer->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_status' => $row->from_status?->value,
            'to_status' => $row->to_status instanceof \BackedEnum
                ? $row->to_status->value
                : (string) $row->to_status,
            'comment' => $row->comment,
            'performed_by' => $actor,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
