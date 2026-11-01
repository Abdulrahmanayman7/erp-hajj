<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationAppealStatus;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Support\EvaluationNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\PublishedCriteriaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AcceptSupplierEvaluationAppeal
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly EvaluationNumberGenerator $numbers,
        private readonly PublishedCriteriaResolver $publishedCriteria,
    ) {}

    public function execute(
        User $actor,
        SupplierEvaluationAppeal $appeal,
        Request $request,
    ): SupplierEvaluationAppeal {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $appeal, $request, $tenant): SupplierEvaluationAppeal {
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
                    'action' => 'accept',
                ], $request);
                throw SupplierDomainException::evaluationAppealSodViolation();
            }

            $pack = $this->publishedCriteria->requirePublishedForCategory((int) $prior->supplier_category_id);

            $draft = new SupplierEvaluation([
                'notes' => null,
                'monitoring_recommended' => false,
                'draft_scores' => null,
            ]);
            $draft->forceFill([
                'evaluation_number' => $this->numbers->next(),
                'supplier_id' => $prior->supplier_id,
                'supplier_category_id' => $prior->supplier_category_id,
                'evaluation_kind' => $prior->evaluation_kind,
                'season_id' => $prior->season_id,
                'status' => EvaluationStatus::Draft,
                'criteria_pack_id' => $pack->id,
                'criteria_pack_version_no' => $pack->version_no,
                'prepared_by' => $actor->id,
                'version_no' => (int) $prior->version_no + 1,
                'evaluation_family_key' => $prior->evaluation_family_key,
                'supersedes_evaluation_id' => $prior->id,
                'origin_appeal_id' => $lockedAppeal->id,
            ]);
            $draft->save();

            $lockedAppeal->forceFill([
                'status' => EvaluationAppealStatus::Accepted,
                'open_slot' => $lockedAppeal->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resulting_evaluation_id' => $draft->id,
            ]);
            $lockedAppeal->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_APPEAL_ACCEPTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_appeal',
                'entity_id' => $lockedAppeal->id,
                'evaluation_id' => $prior->id,
                'resulting_evaluation_id' => $draft->id,
                'version_no' => $draft->version_no,
            ], $request);

            return $lockedAppeal->fresh([
                'filer',
                'reviewer',
                'evaluation',
                'resultingEvaluation.scoreSnapshots',
                'resultingEvaluation.preparer',
                'resultingEvaluation.criteriaPack',
            ]) ?? $lockedAppeal;
        });
    }
}
