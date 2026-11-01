<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationScoreSnapshot;
use App\Modules\SuppliersAndProcurement\Support\EvaluationPreconditions;
use App\Modules\SuppliersAndProcurement\Support\EvaluationScoreCalculator;
use App\Modules\SuppliersAndProcurement\Support\PublishedCriteriaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitSupplierEvaluation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PublishedCriteriaResolver $publishedCriteria,
        private readonly EvaluationPreconditions $preconditions,
        private readonly EvaluationScoreCalculator $calculator,
    ) {}

    public function execute(User $actor, SupplierEvaluation $evaluation, Request $request): SupplierEvaluation
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $evaluation, $request, $tenant): SupplierEvaluation {
            /** @var SupplierEvaluation $locked */
            $locked = SupplierEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::evaluationInvalidStatus();
            }

            $locked->loadMissing('supplier');
            if ($locked->supplier === null || $locked->supplier->status === SupplierStatus::Archived) {
                throw SupplierDomainException::evaluationSupplierArchived();
            }

            $this->preconditions->resolveSeason($locked->evaluation_kind, $locked->season_id);

            $pack = $this->publishedCriteria->requirePublishedForCategory((int) $locked->supplier_category_id);
            $active = $pack->activeCriteria()->orderBy('sort_order')->orderBy('id')->get();
            if ($active->isEmpty()) {
                throw SupplierDomainException::evaluationCriteriaMissing();
            }

            $draftByCriterion = collect($locked->draft_scores ?? [])
                ->keyBy(fn (array $row): int => (int) $row['criterion_id']);

            $snapshotLines = [];
            $calcLines = [];
            $sort = 1;

            foreach ($active as $criterion) {
                /** @var SupplierEvaluationCriterion $criterion */
                $input = $draftByCriterion->get($criterion->id);
                if ($input === null) {
                    throw SupplierDomainException::evaluationScoresIncomplete();
                }

                $score = bcadd((string) $input['score'], '0', 2);
                $this->calculator->assertScoreRange($score);
                $weight = bcadd((string) $criterion->weight, '0', 4);

                $snapshot = new SupplierEvaluationScoreSnapshot;
                $snapshot->forceFill([
                    'evaluation_id' => $locked->id,
                    'source_criterion_id' => $criterion->id,
                    'criterion_name' => $criterion->name,
                    'weight' => $weight,
                    'score' => $score,
                    'note' => $input['note'] ?? null,
                    'sort_order' => $sort++,
                ]);
                $snapshot->save();

                $snapshotLines[] = $snapshot;
                $calcLines[] = ['weight' => $weight, 'score' => $score];
            }

            $total = $this->calculator->total($calcLines);

            $locked->forceFill([
                'status' => EvaluationStatus::PendingApproval,
                'criteria_pack_id' => $pack->id,
                'criteria_pack_version_no' => $pack->version_no,
                'total_score' => $total,
                'submitted_at' => now(),
                'draft_scores' => null,
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_SUBMITTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $locked->id,
                'evaluation_number' => $locked->evaluation_number,
                'total_score' => $total,
                'criteria_pack_id' => $pack->id,
                'criteria_pack_version_no' => $pack->version_no,
                'snapshot_count' => count($snapshotLines),
            ], $request);

            // IIC-F3: submit must NOT emit monitoring notification.
            return $locked->fresh([
                'scoreSnapshots',
                'preparer',
                'approver',
                'category',
                'supplier',
                'season',
                'criteriaPack',
            ]) ?? $locked;
        });
    }
}
