<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivateEmployee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Employee $employee, Request $request): Employee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $employee, $request, $tenant): Employee {
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $locked->status = EmployeeStatus::Active;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_ACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'employee_id' => $locked->id,
                'employee_number' => $locked->employee_number,
            ], $request);

            return $locked->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
        });
    }
}
