<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteSeasonProject
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProject $project, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $project, $request, $tenant): void {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $number = $locked->number;
            $seasonId = $locked->season_id;
            $projectId = $locked->id;

            // Model deleting hooks enforce E-Q14 + dependency guards.
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_SOFT_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $seasonId,
                'project_id' => $projectId,
                'project_number' => $number,
            ], $request);
        });
    }
}
