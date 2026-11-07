<?php

namespace App\Modules\RisksAndComplaints\Resources;

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalComplaintNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationalComplaint
 */
class OperationalComplaintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OperationalComplaint $complaint */
        $complaint = $this->resource;

        $payload = [
            'id' => $complaint->id,
            'season_id' => $complaint->season_id,
            'complaint_number' => $complaint->complaint_number,
            'title' => $complaint->title,
            'body' => $complaint->body,
            'category_code' => $complaint->category_code,
            'status' => $complaint->status->value,
            'assignee_employee_id' => $complaint->assignee_employee_id,
            'escalation_level' => $complaint->escalation_level,
            'escalated_to_employee_id' => $complaint->escalated_to_employee_id,
            'escalate_reason' => $complaint->escalate_reason,
            'escalated_at' => $complaint->escalated_at?->toIso8601String(),
            'resolution_text' => $complaint->resolution_text,
            'resolved_at' => $complaint->resolved_at?->toIso8601String(),
            'resolved_by' => $complaint->resolved_by,
            'closed_at' => $complaint->closed_at?->toIso8601String(),
            'closed_by' => $complaint->closed_by,
            'close_reason' => $complaint->close_reason,
            'reopened_at' => $complaint->reopened_at?->toIso8601String(),
            'reopened_by' => $complaint->reopened_by,
            'reopen_reason' => $complaint->reopen_reason,
            'season_pilgrim_id' => $complaint->season_pilgrim_id,
            'group_id' => $complaint->group_id,
            'facility_id' => $complaint->facility_id,
            'transport_season_vehicle_id' => $complaint->transport_season_vehicle_id,
            'project_id' => $complaint->project_id,
            'created_at' => $complaint->created_at?->toIso8601String(),
            'updated_at' => $complaint->updated_at?->toIso8601String(),
            'created_by' => $this->userPayload(
                $complaint->relationLoaded('createdBy') ? $complaint->createdBy : null,
            ),
            'assignee_employee' => $this->employeePayload(
                $complaint->relationLoaded('assigneeEmployee') ? $complaint->assigneeEmployee : null,
            ),
            'escalated_to_employee' => $this->employeePayload(
                $complaint->relationLoaded('escalatedToEmployee') ? $complaint->escalatedToEmployee : null,
            ),
        ];

        if ($complaint->relationLoaded('notes')) {
            $payload['notes'] = $complaint->notes
                ->map(fn (OperationalComplaintNote $note): array => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'created_by' => $this->userPayload(
                        $note->relationLoaded('createdBy') ? $note->createdBy : null,
                    ),
                    'created_at' => $note->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array{id: int, full_name: string, employee_number: string|null}|null
     */
    private function employeePayload(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_number' => $employee->employee_number ?? null,
        ];
    }
}
