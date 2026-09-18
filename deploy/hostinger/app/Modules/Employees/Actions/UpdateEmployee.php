<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Support\EmployeeReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateEmployee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmployeeReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *     full_name?: string,
     *     organization_unit_id?: int,
     *     position_id?: int|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     hire_date?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(User $actor, Employee $employee, array $data, Request $request): Employee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $employee, $data, $request, $tenant): Employee {
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $oldOrganizationUnitId = (int) $locked->organization_unit_id;

            if (array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null) {
                $unit = $this->references->resolveActiveOrganizationUnit((int) $data['organization_unit_id']);
                $locked->organization_unit_id = $unit->id;
            }

            if (array_key_exists('position_id', $data)) {
                $positionId = $data['position_id'] !== null ? (int) $data['position_id'] : null;
                $position = $this->references->resolvePositionForUpdate($positionId, $locked->position_id);
                $locked->position_id = $position?->id;
            }

            foreach (['full_name', 'phone', 'email', 'hire_date', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $locked->{$field} = $data[$field];
                }
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'employee_id' => $locked->id,
                'employee_number' => $locked->employee_number,
            ], $request);

            if ((int) $locked->organization_unit_id !== $oldOrganizationUnitId) {
                $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_ORGANIZATION_CHANGED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'employee_id' => $locked->id,
                    'old_organization_unit_id' => $oldOrganizationUnitId,
                    'new_organization_unit_id' => (int) $locked->organization_unit_id,
                ], $request);
            }

            return $locked->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
        });
    }
}
