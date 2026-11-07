<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phases are informational labels only (E-Q03 = A). Status changes do not mutate tasks.
 */
final class UpdateSeasonProjectPhase
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonProjectPhase $phase, array $data, Request $request): SeasonProjectPhase
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $phase, $data, $request, $tenant): SeasonProjectPhase {
                /** @var SeasonProjectPhase $locked */
                $locked = SeasonProjectPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();

                $payload = [];
                foreach (['title', 'starts_on', 'ends_on', 'sort_order'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $data[$field];
                    }
                }
                if (array_key_exists('status', $data) && is_string($data['status']) && $data['status'] !== '') {
                    $payload['status'] = SeasonProjectPhaseStatus::from($data['status']);
                }

                $locked->forceFill($payload)->save();

                $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PHASE_UPDATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->project_id,
                    'phase_id' => $locked->id,
                ], $request);

                return $locked;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'season_project_phases_project_sort_unique')) {
                throw SeasonProjectDomainException::phaseSortConflict();
            }
            throw $e;
        }
    }
}
