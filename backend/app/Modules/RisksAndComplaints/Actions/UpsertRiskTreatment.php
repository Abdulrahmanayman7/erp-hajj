<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\TreatmentStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Models\OperationalRiskTreatment;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpsertRiskTreatment
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, OperationalRisk $risk, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $risk, $data, $request, $tenant): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertCanManageTreatment($locked->status);

            $employee = $this->references->resolveActiveEmployee((int) $data['owner_employee_id']);
            $status = isset($data['status']) && is_string($data['status']) && $data['status'] !== ''
                ? TreatmentStatus::from($data['status'])
                : TreatmentStatus::Planned;

            $existing = OperationalRiskTreatment::query()
                ->where('risk_id', $locked->id)
                ->lockForUpdate()
                ->first();

            $before = $existing === null ? null : [
                'title' => $existing->title,
                'plan_body' => $existing->plan_body,
                'owner_employee_id' => $existing->owner_employee_id,
                'due_at' => $existing->due_at?->toIso8601String(),
                'status' => $existing->status->value,
            ];

            if ($existing === null) {
                $existing = new OperationalRiskTreatment([
                    'risk_id' => $locked->id,
                    'created_by' => $actor->id,
                ]);
            }

            $existing->title = trim((string) $data['title']);
            $existing->plan_body = (string) $data['plan_body'];
            $existing->owner_employee_id = $employee->id;
            $existing->due_at = $data['due_at'] ?? null;
            $existing->status = $status;
            $existing->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_TREATMENT_UPSERTED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'treatment_id' => $existing->id,
                'before' => $before,
                'after' => [
                    'title' => $existing->title,
                    'plan_body' => $existing->plan_body,
                    'owner_employee_id' => $existing->owner_employee_id,
                    'due_at' => $existing->due_at?->toIso8601String(),
                    'status' => $existing->status->value,
                ],
            ], $request);

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
