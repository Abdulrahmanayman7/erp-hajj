<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Support\EvaluationPreconditions;
use App\Modules\SuppliersAndProcurement\Support\EvaluationScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSupplierEvaluation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly EvaluationPreconditions $preconditions,
        private readonly EvaluationScoreCalculator $scores,
    ) {}

    /**
     * @param  array<string, mixed>  $data
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

            if (! $locked->isDraft()) {
                throw SupplierDomainException::evaluationNotDraft();
            }

            $kind = isset($data['evaluation_kind'])
                ? EvaluationKind::from((string) $data['evaluation_kind'])
                : $locked->evaluation_kind;

            $seasonId = array_key_exists('season_id', $data)
                ? ($data['season_id'] !== null ? (int) $data['season_id'] : null)
                : $locked->season_id;

            $season = $this->preconditions->resolveSeason($kind, $seasonId);

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }
            if (array_key_exists('monitoring_recommended', $data)) {
                $locked->monitoring_recommended = (bool) $data['monitoring_recommended'];
            }
            if (array_key_exists('scores', $data)) {
                $locked->draft_scores = $this->normalizeDraftScores($data['scores']);
            }

            $locked->forceFill([
                'evaluation_kind' => $kind,
                'season_id' => $season?->id,
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $locked->id,
                'evaluation_number' => $locked->evaluation_number,
                'status' => $locked->status->value,
                'monitoring_recommended' => $locked->monitoring_recommended,
            ], $request);

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
