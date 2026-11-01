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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FileSupplierEvaluationAppeal
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
    ): SupplierEvaluationAppeal {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $evaluation, $data, $request, $tenant): SupplierEvaluationAppeal {
            /** @var SupplierEvaluation $locked */
            $locked = SupplierEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isApproved()) {
                throw SupplierDomainException::evaluationAppealNotApprovedTarget();
            }

            $appeal = new SupplierEvaluationAppeal([
                'reason' => trim((string) $data['reason']),
            ]);
            $appeal->forceFill([
                'evaluation_id' => $locked->id,
                'status' => EvaluationAppealStatus::Open,
                'open_slot' => 0,
                'filed_by' => $actor->id,
                'filed_at' => now(),
            ]);

            try {
                $appeal->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::evaluationAppealExists();
            }

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_APPEAL_FILED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_appeal',
                'entity_id' => $appeal->id,
                'evaluation_id' => $locked->id,
            ], $request);

            return $appeal->fresh(['filer', 'evaluation', 'resultingEvaluation']) ?? $appeal;
        });
    }
}
