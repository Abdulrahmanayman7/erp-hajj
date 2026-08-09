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

final class AssignEmployeeSupervisor
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmployeeReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Employee $employee, ?int $supervisorId, Request $request): Employee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $employee, $supervisorId, $request, $tenant): Employee {
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $oldSupervisorId = $locked->supervisor_id;

            $supervisor = $this->references->resolveAssignableSupervisor($supervisorId, $locked);
            $locked->supervisor_id = $supervisor?->id;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_SUPERVISOR_CHANGED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'employee_id' => $locked->id,
                'old_supervisor_id' => $oldSupervisorId,
                'new_supervisor_id' => $locked->supervisor_id,
            ], $request);

            return $locked->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
        });
    }
}
