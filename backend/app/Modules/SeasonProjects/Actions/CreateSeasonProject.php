<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSeasonProject
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonProject
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonProject {
            $project = new SeasonProject;
            $project->forceFill([
                'season_id' => $season->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => SeasonProjectStatus::Draft,
                'owner_employee_id' => $data['owner_employee_id'] ?? null,
                'budget_amount' => $data['budget_amount'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'progress_percent' => 0,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $project->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $season->id,
                'project_id' => $project->id,
                'project_number' => $project->number,
                'status' => SeasonProjectStatus::Draft->value,
            ], $request);

            return $project->fresh(['ownerEmployee']) ?? $project;
        });
    }
}
