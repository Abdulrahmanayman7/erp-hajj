<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Support\EvaluationNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\EvaluationPreconditions;
use App\Modules\SuppliersAndProcurement\Support\EvaluationScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateSupplierEvaluation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly EvaluationPreconditions $preconditions,
        private readonly EvaluationNumberGenerator $numbers,
        private readonly EvaluationScoreCalculator $scores,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): SupplierEvaluation
    {
        $tenant = $this->tenantContext->require();
        $kind = EvaluationKind::from((string) $data['evaluation_kind']);
        $seasonId = isset($data['season_id']) ? (int) $data['season_id'] : null;

        // Bounded InnoDB deadlock / lock-wait retries. Numbering correctness is
        // the sequence primary-key upsert; attempts recover transient contention.
        return DB::transaction(function () use ($actor, $data, $request, $tenant, $kind, $seasonId): SupplierEvaluation {
            $ctx = $this->preconditions->assertCreatable(
                (int) $data['supplier_id'],
                (int) $data['supplier_category_id'],
                $kind,
                $seasonId,
            );

            $draftScores = $this->normalizeDraftScores($data['scores'] ?? null);

            $evaluation = new SupplierEvaluation([
                'notes' => $data['notes'] ?? null,
                'monitoring_recommended' => (bool) ($data['monitoring_recommended'] ?? false),
                'draft_scores' => $draftScores,
            ]);
            $evaluation->forceFill([
                'evaluation_number' => $this->numbers->next(),
                'supplier_id' => $ctx['supplier']->id,
                'supplier_category_id' => (int) $data['supplier_category_id'],
                'evaluation_kind' => $kind,
                'season_id' => $ctx['season']?->id,
                'status' => EvaluationStatus::Draft,
                'criteria_pack_id' => $ctx['pack']->id,
                'criteria_pack_version_no' => $ctx['pack']->version_no,
                'prepared_by' => $actor->id,
                'version_no' => 1,
                'evaluation_family_key' => (string) Str::uuid(),
            ]);
            $evaluation->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $evaluation->id,
                'evaluation_number' => $evaluation->evaluation_number,
                'supplier_id' => $evaluation->supplier_id,
                'status' => $evaluation->status->value,
                'version_no' => $evaluation->version_no,
            ], $request);

            return $evaluation->fresh([
                'scoreSnapshots',
                'preparer',
                'approver',
                'category',
                'supplier',
                'season',
                'criteriaPack',
            ]) ?? $evaluation;
        }, 5);
    }

    /**
     * @return list<array{criterion_id: int, score: string, note: ?string}>|null
     */
    private function normalizeDraftScores(mixed $scores): ?array
    {
        if ($scores === null) {
            return null;
        }

        if (! is_array($scores)) {
            return null;
        }

        $normalized = [];
        foreach ($scores as $row) {
            if (! is_array($row) || ! isset($row['criterion_id'], $row['score'])) {
                continue;
            }
            $score = bcadd((string) $row['score'], '0', 2);
            $this->scores->assertScoreRange($score);
            $normalized[] = [
                'criterion_id' => (int) $row['criterion_id'],
                'score' => $score,
                'note' => isset($row['note']) ? (string) $row['note'] : null,
            ];
        }

        return $normalized;
    }
}
