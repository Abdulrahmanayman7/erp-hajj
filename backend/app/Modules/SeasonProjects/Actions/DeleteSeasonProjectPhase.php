<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * E-Q03 = A: delete phase unlinks tasks (phase_id → NULL); preserve project_id/season_id.
 * Does not delete or cancel tasks.
 */
final class DeleteSeasonProjectPhase
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProjectPhase $phase, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $phase, $request, $tenant): void {
            /** @var SeasonProjectPhase $locked */
            $locked = SeasonProjectPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();

            $phaseId = (int) $locked->id;
            $projectId = (int) $locked->project_id;
            $seasonId = (int) $locked->season_id;

            // Unlink before delete — FK is RESTRICT.
            Task::query()
                ->where('phase_id', $phaseId)
                ->lockForUpdate()
                ->update(['phase_id' => null]);

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PHASE_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $seasonId,
                'project_id' => $projectId,
                'phase_id' => $phaseId,
                'tasks_unlinked' => true,
            ], $request);
        });
    }
}
