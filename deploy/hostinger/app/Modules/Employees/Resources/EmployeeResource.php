<?php

namespace App\Modules\Employees\Resources;

use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'status' => $employee->status->value,
            'hire_date' => $employee->hire_date?->format('Y-m-d'),
            'notes' => $employee->notes,
            'organization_unit' => $this->organizationUnitPayload($employee),
            'position' => $this->positionPayload($employee),
            'supervisor' => $this->supervisorPayload($employee),
            'user' => $this->userPayload($employee),
            'created_at' => $employee->created_at?->toIso8601String(),
            'updated_at' => $employee->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function organizationUnitPayload(Employee $employee): ?array
    {
        if (! $employee->relationLoaded('organizationUnit') || $employee->organizationUnit === null) {
            return null;
        }

        $unit = $employee->organizationUnit;

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'code' => $unit->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function positionPayload(Employee $employee): ?array
    {
        if (! $employee->relationLoaded('position') || $employee->position === null) {
            return null;
        }

        $position = $employee->position;

        return [
            'id' => $position->id,
            'name' => $position->name,
            'code' => $position->code,
        ];
    }

    /**
     * @return array{id: int, employee_number: string, full_name: string, organization_unit: array{id: int, name: string, code: string}|null}|null
     */
    private function supervisorPayload(Employee $employee): ?array
    {
        if (! $employee->relationLoaded('supervisor') || $employee->supervisor === null) {
            return null;
        }

        $supervisor = $employee->supervisor;
        $unit = null;
        if ($supervisor->relationLoaded('organizationUnit') && $supervisor->organizationUnit !== null) {
            $unit = [
                'id' => $supervisor->organizationUnit->id,
                'name' => $supervisor->organizationUnit->name,
                'code' => $supervisor->organizationUnit->code,
            ];
        }

        return [
            'id' => $supervisor->id,
            'employee_number' => $supervisor->employee_number,
            'full_name' => $supervisor->full_name,
            'organization_unit' => $unit,
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, status: string}|null
     */
    private function userPayload(Employee $employee): ?array
    {
        if (! $employee->relationLoaded('user') || $employee->user === null) {
            return null;
        }

        $user = $employee->user;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
        ];
    }
}
