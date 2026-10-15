<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonPilgrims\Enums\SupervisorRole;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F27: assigns an existing active Employee to a group's supervisor team.
 * Assigning a primary while one exists ends the previous primary (history
 * kept). Assistants require a current primary. No permission is granted.
 */
final class AssignPilgrimGroupSupervisor
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrimGroup $group, int $employeeId, SupervisorRole $role, Request $request): SeasonPilgrimGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $employeeId, $role, $request, $tenant): SeasonPilgrimGroup {
            /** @var SeasonPilgrimGroup $locked */
            $locked = SeasonPilgrimGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $employee = Employee::query()->whereKey($employeeId)->first();
            if ($employee === null || $employee->status !== EmployeeStatus::Active) {
                throw SeasonPilgrimDomainException::supervisorEmployeeInvalid();
            }

            if ($locked->currentSupervisors()->where('employee_id', $employee->id)->exists()) {
                throw SeasonPilgrimDomainException::supervisorDuplicate();
            }

            /** @var SeasonPilgrimGroupSupervisor|null $currentPrimary */
            $currentPrimary = $locked->currentSupervisors()
                ->where('role', SupervisorRole::Primary->value)
                ->lockForUpdate()
                ->first();

            if ($role === SupervisorRole::Assistant && $currentPrimary === null) {
                throw SeasonPilgrimDomainException::primarySupervisorRequired();
            }

            $now = now();
            $replaced = null;
            if ($role === SupervisorRole::Primary && $currentPrimary !== null) {
                $currentPrimary->update(['ended_at' => $now, 'ended_by' => $actor->id]);
                $replaced = $currentPrimary;
            }

            try {
                $assignment = SeasonPilgrimGroupSupervisor::query()->create([
                    'season_pilgrim_group_id' => $locked->id,
                    'employee_id' => $employee->id,
                    'role' => $role,
                    'assigned_at' => $now,
                    'assigned_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SeasonPilgrimDomainException::supervisorDuplicate();
            }

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_GROUP_SUPERVISOR_ASSIGNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_group_supervisor_id' => $assignment->id,
                'employee_name' => $employee->full_name,
                'after_values' => ['employee_id' => $employee->id, 'role' => $role->value],
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'season_pilgrim_group_id' => $locked->id,
                    'replaced_supervisor_assignment_id' => $replaced?->id,
                    'replaced_employee_id' => $replaced?->employee_id,
                ],
            ], $request);

            return PilgrimGroupLoader::load($locked);
        });
    }
}
