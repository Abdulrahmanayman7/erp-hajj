<?php

namespace App\Modules\RisksAndComplaints\Resources;

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Models\OperationalRiskTreatment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationalRisk
 */
class OperationalRiskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OperationalRisk $risk */
        $risk = $this->resource;

        return [
            'id' => $risk->id,
            'season_id' => $risk->season_id,
            'risk_number' => $risk->risk_number,
            'title' => $risk->title,
            'description' => $risk->description,
            'status' => $risk->status->value,
            'likelihood' => $risk->likelihood,
            'impact' => $risk->impact,
            'score' => $risk->score,
            'severity_level' => $risk->severity_level?->value,
            'closure_outcome' => $risk->closure_outcome?->value,
            'close_reason' => $risk->close_reason,
            'owner_employee_id' => $risk->owner_employee_id,
            'season_pilgrim_id' => $risk->season_pilgrim_id,
            'group_id' => $risk->group_id,
            'facility_id' => $risk->facility_id,
            'transport_season_vehicle_id' => $risk->transport_season_vehicle_id,
            'project_id' => $risk->project_id,
            'approved_at' => $risk->approved_at?->toIso8601String(),
            'created_at' => $risk->created_at?->toIso8601String(),
            'updated_at' => $risk->updated_at?->toIso8601String(),
            'created_by' => $this->userPayload(
                $risk->relationLoaded('createdBy') ? $risk->createdBy : null,
            ),
            'approved_by' => $this->userPayload(
                $risk->relationLoaded('approvedBy') ? $risk->approvedBy : null,
            ),
            'owner_employee' => $this->employeePayload(
                $risk->relationLoaded('ownerEmployee') ? $risk->ownerEmployee : null,
            ),
            'treatment' => $this->treatmentPayload(
                $risk->relationLoaded('treatment') ? $risk->treatment : null,
            ),
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    private function treatmentPayload(?OperationalRiskTreatment $treatment): ?array
    {
        if ($treatment === null) {
            return null;
        }

        return [
            'id' => $treatment->id,
            'title' => $treatment->title,
            'plan_body' => $treatment->plan_body,
            'owner_employee_id' => $treatment->owner_employee_id,
            'due_at' => $treatment->due_at?->toIso8601String(),
            'status' => $treatment->status->value,
            'owner_employee' => $this->employeePayload(
                $treatment->relationLoaded('ownerEmployee') ? $treatment->ownerEmployee : null,
            ),
        ];
    }
}
