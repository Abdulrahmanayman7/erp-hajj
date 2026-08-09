<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Support\EmployeeNumberGenerator;
use App\Modules\Employees\Support\EmployeeReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateEmployee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmployeeNumberGenerator $numbers,
        private readonly EmployeeReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *     full_name: string,
     *     organization_unit_id: int,
     *     position_id?: int|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     hire_date?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): Employee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Employee {
            $unit = $this->references->resolveActiveOrganizationUnit((int) $data['organization_unit_id']);
            $positionId = array_key_exists('position_id', $data) && $data['position_id'] !== null
                ? (int) $data['position_id']
                : null;
            $position = $this->references->resolveAssignablePosition($positionId);

            $employee = new Employee([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'organization_unit_id' => $unit->id,
                'position_id' => $position?->id,
                'status' => EmployeeStatus::Active,
                'hire_date' => $data['hire_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $employee->employee_number = $this->numbers->next();
            $employee->save();

            $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'organization_unit_id' => $employee->organization_unit_id,
                'position_id' => $employee->position_id,
            ], $request);

            return $employee->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
        });
    }
}
