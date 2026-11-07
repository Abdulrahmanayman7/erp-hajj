<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSeasonProjectPhase
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonProject $project, array $data, Request $request): SeasonProjectPhase
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $project, $data, $request, $tenant): SeasonProjectPhase {
                /** @var SeasonProject $locked */
                $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                $sortOrder = array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                    ? (int) $data['sort_order']
                    : (int) (SeasonProjectPhase::query()->where('project_id', $locked->id)->max('sort_order') ?? -1) + 1;

                $status = SeasonProjectPhaseStatus::Planned;
                if (isset($data['status']) && is_string($data['status']) && $data['status'] !== '') {
                    $status = SeasonProjectPhaseStatus::from($data['status']);
                }

                $phase = new SeasonProjectPhase;
                $phase->forceFill([
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->id,
                    'title' => $data['title'],
                    'sort_order' => $sortOrder,
                    'starts_on' => $data['starts_on'] ?? null,
                    'ends_on' => $data['ends_on'] ?? null,
                    'status' => $status,
                ]);
                $phase->save();

                $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PHASE_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->id,
                    'phase_id' => $phase->id,
                    'sort_order' => $phase->sort_order,
                ], $request);

                return $phase;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'season_project_phases_project_sort_unique')) {
                throw SeasonProjectDomainException::phaseSortConflict();
            }
            throw $e;
        }
    }
}
