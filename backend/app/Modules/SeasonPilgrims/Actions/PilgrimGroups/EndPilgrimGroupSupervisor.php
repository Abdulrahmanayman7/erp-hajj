<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SupervisorRole;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ends a supervisor assignment (row kept as history). The primary cannot
 * be ended while assistants remain — replace it instead (F27).
 */
final class EndPilgrimGroupSupervisor
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrimGroup $group, SeasonPilgrimGroupSupervisor $assignment, Request $request): SeasonPilgrimGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $assignment, $request, $tenant): SeasonPilgrimGroup {
            /** @var SeasonPilgrimGroup $locked */
            $locked = SeasonPilgrimGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            /** @var SeasonPilgrimGroupSupervisor $row */
            $row = $locked->supervisors()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($row->ended_at !== null) {
                throw SeasonPilgrimDomainException::supervisorAssignmentEnded();
            }

            if ($row->role === SupervisorRole::Primary
                && $locked->currentSupervisors()->where('role', SupervisorRole::Assistant->value)->exists()) {
                throw SeasonPilgrimDomainException::primarySupervisorEndBlocked();
            }

            $row->update(['ended_at' => now(), 'ended_by' => $actor->id]);

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_GROUP_SUPERVISOR_ENDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_group_supervisor_id' => $row->id,
                'employee_name' => $row->employee?->full_name,
                'before_values' => ['employee_id' => $row->employee_id, 'role' => $row->role->value],
                'metadata' => ['season_id' => $locked->season_id, 'season_pilgrim_group_id' => $locked->id],
            ], $request);

            return PilgrimGroupLoader::load($locked);
        });
    }
}
