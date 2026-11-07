<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Models\OperationalRiskTreatment;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApproveRiskAssessment
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    public function execute(User $actor, OperationalRisk $risk, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $risk, $request, $tenant): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertCanApprove($locked->status);

            if ((int) $locked->created_by === (int) $actor->id) {
                throw RisksAndComplaintsDomainException::riskSelfApprovalForbidden();
            }

            if ($locked->likelihood === null || $locked->impact === null) {
                throw RisksAndComplaintsDomainException::riskAssessmentIncomplete();
            }

            $treatment = OperationalRiskTreatment::query()
                ->where('risk_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($treatment === null) {
                throw RisksAndComplaintsDomainException::riskTreatmentRequired();
            }

            $beforeStatus = $locked->status->value;
            $locked->status = RiskStatus::Approved;
            $locked->approved_by = $actor->id;
            $locked->approved_at = now();
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_ASSESSMENT_APPROVED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => ['status' => $beforeStatus],
                'after' => [
                    'status' => $locked->status->value,
                    'approved_by' => $locked->approved_by,
                    'approved_at' => $locked->approved_at?->toIso8601String(),
                    'severity_level' => $locked->severity_level?->value,
                    'score' => $locked->score,
                ],
            ], $request);

            if ($locked->severity_level?->isAlertBand()) {
                $occurrenceId = $this->occurrences->record(
                    OperationalNotificationOccurrenceRecorder::ENTITY_RISK,
                    (int) $locked->id,
                    OperationalNotificationOccurrenceRecorder::KIND_SEVERITY_APPROVE,
                );
                $this->notifications->riskSeverityAlert($locked, $occurrenceId, mode: 'approve');
            }

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
