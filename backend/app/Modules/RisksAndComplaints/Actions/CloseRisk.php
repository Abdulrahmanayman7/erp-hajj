<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\RiskClosureOutcome;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CloseRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{closure_outcome: string, close_reason?: string|null}  $data
     */
    public function execute(User $actor, OperationalRisk $risk, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $risk, $data, $request, $tenant): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertCanClose($locked->status);

            $outcomeRaw = $data['closure_outcome'] ?? null;
            if (! is_string($outcomeRaw) || $outcomeRaw === '') {
                throw RisksAndComplaintsDomainException::riskClosureOutcomeRequired();
            }

            $outcome = RiskClosureOutcome::tryFrom($outcomeRaw);
            if ($outcome === null) {
                throw RisksAndComplaintsDomainException::riskClosureOutcomeRequired();
            }

            $before = [
                'status' => $locked->status->value,
                'closure_outcome' => $locked->closure_outcome?->value,
                'close_reason' => $locked->close_reason,
            ];

            $locked->status = RiskStatus::Closed;
            $locked->closure_outcome = $outcome;
            $locked->close_reason = $data['close_reason'] ?? null;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_CLOSED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'closure_outcome' => $locked->closure_outcome->value,
                    'close_reason' => $locked->close_reason,
                ],
            ], $request);

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
