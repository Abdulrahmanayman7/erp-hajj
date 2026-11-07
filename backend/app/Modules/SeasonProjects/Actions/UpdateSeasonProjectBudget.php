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

final class UpdateSeasonProjectBudget
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly GetSeasonProjectBudget $reader,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(User $actor, SeasonProject $project, array $data, Request $request): array
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $data, $request, $tenant): array {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [SeasonProjectStatus::Draft, SeasonProjectStatus::Active], true)) {
                throw SeasonProjectDomainException::budgetUpdateNotAllowed();
            }

            $payload = ['updated_by' => $actor->id];

            if (array_key_exists('budget_amount', $data)) {
                $payload['budget_amount'] = $data['budget_amount'];
            }

            if (array_key_exists('currency_code', $data) && $data['currency_code'] !== null) {
                $nextCurrency = strtoupper((string) $data['currency_code']);
                if ($nextCurrency !== strtoupper((string) $locked->currency_code)
                    && $locked->budgetPoLinks()->exists()) {
                    throw SeasonProjectDomainException::budgetCurrencyLocked();
                }
                $payload['currency_code'] = $nextCurrency;
            }

            $locked->forceFill($payload)->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_BUDGET_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->season_id,
                'project_id' => $locked->id,
                'budget_amount' => $locked->budget_amount,
                'currency_code' => $locked->currency_code,
            ], $request);

            return $this->reader->execute($locked->fresh() ?? $locked);
        });
    }
}
