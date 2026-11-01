<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationAppealStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RejectSupplierEvaluationAppeal
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{review_outcome_note?: string|null}  $data
     */
    public function execute(
        User $actor,
        SupplierEvaluationAppeal $appeal,
        array $data,
        Request $request,
    ): SupplierEvaluationAppeal {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $appeal, $data, $request, $tenant): SupplierEvaluationAppeal {
            /** @var SupplierEvaluationAppeal $lockedAppeal */
            $lockedAppeal = SupplierEvaluationAppeal::query()->whereKey($appeal->id)->lockForUpdate()->firstOrFail();

            if (! $lockedAppeal->isOpen()) {
                throw SupplierDomainException::evaluationAppealNotOpen();
            }

            /** @var SupplierEvaluation $prior */
            $prior = SupplierEvaluation::query()
                ->whereKey($lockedAppeal->evaluation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedAppeal->filed_by === (int) $actor->id
                || (int) $prior->prepared_by === (int) $actor->id) {
                $this->security->record(AuthorizationSecurityEvent::EVALUATION_APPEAL_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_evaluation_appeal',
                    'entity_id' => $lockedAppeal->id,
                    'action' => 'reject',
                ], $request);
                throw SupplierDomainException::evaluationAppealSodViolation();
            }

            $lockedAppeal->forceFill([
                'status' => EvaluationAppealStatus::Rejected,
                'open_slot' => $lockedAppeal->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_outcome_note' => isset($data['review_outcome_note'])
                    ? trim((string) $data['review_outcome_note'])
                    : null,
            ]);
            $lockedAppeal->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_APPEAL_REJECTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_appeal',
                'entity_id' => $lockedAppeal->id,
                'evaluation_id' => $prior->id,
            ], $request);

            return $lockedAppeal->fresh(['filer', 'reviewer', 'evaluation', 'resultingEvaluation']) ?? $lockedAppeal;
        });
    }
}
