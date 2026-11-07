<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Support\SeasonProjectOwnerValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReassignSeasonProjectOwner
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonProjectOwnerValidator $ownerValidator,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProject $project, int $ownerEmployeeId, Request $request): SeasonProject
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $ownerEmployeeId, $request, $tenant): SeasonProject {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SeasonProjectStatus::Active) {
                throw SeasonProjectDomainException::invalidTransition();
            }

            $owner = $this->ownerValidator->requireActiveSameTenant($ownerEmployeeId);
            $previous = $locked->owner_employee_id;

            $locked->forceFill([
                'owner_employee_id' => $owner->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_OWNER_REASSIGNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->season_id,
                'project_id' => $locked->id,
                'project_number' => $locked->number,
                'from_owner_employee_id' => $previous,
                'to_owner_employee_id' => $owner->id,
            ], $request);

            return $locked->fresh(['ownerEmployee']) ?? $locked;
        });
    }
}
