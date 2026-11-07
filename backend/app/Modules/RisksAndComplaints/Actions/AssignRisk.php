<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AssignRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{owner_employee_id: int}  $data
     */
    public function execute(User $actor, OperationalRisk $risk, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $risk, $data, $request, $tenant): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertCanAssign($locked->status);

            $employee = $this->references->resolveActiveEmployee((int) $data['owner_employee_id']);

            $before = [
                'status' => $locked->status->value,
                'owner_employee_id' => $locked->owner_employee_id,
            ];

            $locked->owner_employee_id = $employee->id;
            $locked->status = RiskStatus::Treating;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_ASSIGNED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'owner_employee_id' => $locked->owner_employee_id,
                ],
            ], $request);

            $occurrenceId = $this->occurrences->record(
                OperationalNotificationOccurrenceRecorder::ENTITY_RISK,
                (int) $locked->id,
                OperationalNotificationOccurrenceRecorder::KIND_ASSIGN,
            );
            $this->notifications->riskAssigned($locked, $occurrenceId);

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
