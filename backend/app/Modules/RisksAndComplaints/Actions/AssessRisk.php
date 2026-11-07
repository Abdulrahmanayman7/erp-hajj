<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use App\Modules\RisksAndComplaints\Support\RiskScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AssessRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskScoreCalculator $scores,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{likelihood: int, impact: int}  $data
     */
    public function execute(User $actor, OperationalRisk $risk, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $risk, $data, $request, $tenant): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertCanAssess($locked->status);

            $likelihood = (int) $data['likelihood'];
            $impact = (int) $data['impact'];
            $computed = $this->scores->calculate($likelihood, $impact);

            $previousSeverity = $locked->severity_level instanceof RiskSeverity
                ? $locked->severity_level
                : null;

            $before = [
                'status' => $locked->status->value,
                'likelihood' => $locked->likelihood,
                'impact' => $locked->impact,
                'score' => $locked->score,
                'severity_level' => $locked->severity_level?->value,
            ];

            $locked->likelihood = $likelihood;
            $locked->impact = $impact;
            $locked->score = $computed['score'];
            $locked->severity_level = $computed['severity'];
            $locked->status = RiskStatus::UnderAssessment;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_ASSESSED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'likelihood' => $locked->likelihood,
                    'impact' => $locked->impact,
                    'score' => $locked->score,
                    'severity_level' => $locked->severity_level?->value,
                ],
            ], $request);

            $newSeverity = $locked->severity_level;
            $enteredOrCrossed = $newSeverity !== null
                && $newSeverity->isAlertBand()
                && ($previousSeverity === null
                    || ! $previousSeverity->isAlertBand()
                    || $previousSeverity !== $newSeverity);

            if ($enteredOrCrossed) {
                $occurrenceId = $this->occurrences->record(
                    OperationalNotificationOccurrenceRecorder::ENTITY_RISK,
                    (int) $locked->id,
                    OperationalNotificationOccurrenceRecorder::KIND_SEVERITY_ASSESS,
                );
                $this->notifications->riskSeverityAlert(
                    $locked,
                    $occurrenceId,
                    previousSeverity: $previousSeverity,
                    mode: 'assess',
                );
            }

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
