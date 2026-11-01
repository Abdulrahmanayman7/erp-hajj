<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RejectSupplierEvaluation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function execute(
        User $actor,
        SupplierEvaluation $evaluation,
        array $data,
        Request $request,
    ): SupplierEvaluation {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $evaluation, $data, $request, $tenant): SupplierEvaluation {
            /** @var SupplierEvaluation $locked */
            $locked = SupplierEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingApproval()) {
                throw SupplierDomainException::evaluationInvalidStatus();
            }

            if ((int) $locked->prepared_by === (int) $actor->id) {
                $this->security->record(AuthorizationSecurityEvent::EVALUATION_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_evaluation',
                    'entity_id' => $locked->id,
                    'action' => 'reject',
                ], $request);
                throw SupplierDomainException::evaluationSodViolation();
            }

            $locked->forceFill([
                'status' => EvaluationStatus::Rejected,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => trim((string) $data['reason']),
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_REJECTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $locked->id,
                'evaluation_number' => $locked->evaluation_number,
            ], $request);

            return $locked->fresh([
                'scoreSnapshots',
                'preparer',
                'approver',
                'rejector',
                'category',
                'supplier',
                'season',
                'criteriaPack',
            ]) ?? $locked;
        });
    }
}
