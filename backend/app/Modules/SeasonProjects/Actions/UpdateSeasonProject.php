<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSeasonProject
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonProject $project, array $data, Request $request): SeasonProject
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $data, $request, $tenant): SeasonProject {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [SeasonProjectStatus::Draft, SeasonProjectStatus::Active], true)) {
                throw SeasonProjectDomainException::updateNotAllowed();
            }

            $payload = [
                'updated_by' => $actor->id,
            ];

            foreach (['title', 'description', 'budget_amount', 'currency_code'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            // Owner may be set/changed on draft via update; active uses reassign-owner.
            if ($locked->status === SeasonProjectStatus::Draft && array_key_exists('owner_employee_id', $data)) {
                $payload['owner_employee_id'] = $data['owner_employee_id'];
            }

            $locked->forceFill($payload)->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->season_id,
                'project_id' => $locked->id,
                'project_number' => $locked->number,
                'status' => $locked->status->value,
            ], $request);

            return $locked->fresh(['ownerEmployee']) ?? $locked;
        });
    }
}
