<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Exceptions\DecisionDomainException;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Support\DecisionLifecycle;
use App\Modules\Decisions\Support\DecisionTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared atomic lifecycle transition: lock → validate → update → history → audit.
 */
final class TransitionDecision
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DecisionTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $auditExtra
     */
    public function execute(
        ?User $actor,
        Decision $decision,
        DecisionStatus $to,
        ?string $comment,
        string $auditEvent,
        ?Request $request = null,
        array $auditExtra = [],
    ): Decision {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $decision, $to, $comment, $auditEvent, $request, $auditExtra, $tenant): Decision {
            $locked = Decision::query()->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            DecisionLifecycle::assertAllowed($from, $to);

            if (DecisionLifecycle::requiresComment($from, $to) && ($comment === null || trim($comment) === '')) {
                throw DecisionDomainException::commentRequired();
            }

            $locked->status = $to;
            $locked->save();

            $this->transitions->record(
                $locked,
                $from,
                $to,
                $actor,
                $comment !== null ? trim($comment) : null,
                $request,
            );

            $this->security->record($auditEvent, array_merge([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor?->id,
                'decision_id' => $locked->id,
                'decision_number' => $locked->decision_number,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $auditExtra), $request);

            return $this->loadRelations($locked);
        });
    }

    private function loadRelations(Decision $decision): Decision
    {
        return $decision->load([
            'organizationUnit',
            'issuedByEmployee',
            'responsibleEmployee',
            'createdBy',
            'sourceRecommendation.meeting',
            'statusTransitions.performer',
        ]);
    }
}
