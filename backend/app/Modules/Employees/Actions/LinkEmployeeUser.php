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

final class LinkEmployeeUser
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmployeeReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Employee $employee, ?int $userId, Request $request): Employee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $employee, $userId, $request, $tenant): Employee {
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $oldUserId = $locked->user_id;

            if ($userId === null) {
                $locked->user_id = null;
                $locked->save();

                $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_USER_UNLINKED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'employee_id' => $locked->id,
                    'old_user_id' => $oldUserId,
                ], $request);
            } else {
                // Same user already linked — idempotent no-op without re-validating status.
                if ((int) $oldUserId === $userId) {
                    return $locked->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
                }

                $user = $this->references->resolveLinkableUser($userId, $locked->id);
                $locked->user_id = $user->id;
                $locked->save();

                $this->security->record(AuthorizationSecurityEvent::EMPLOYEE_USER_LINKED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'employee_id' => $locked->id,
                    'old_user_id' => $oldUserId,
                    'new_user_id' => $locked->user_id,
                ], $request);
            }

            return $locked->load(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);
        });
    }
}
