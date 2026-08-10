<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use Illuminate\Http\Request;

final class ReturnDecisionToDraft
{
    public function __construct(
        private readonly TransitionDecision $transition,
    ) {}

    public function execute(User $actor, Decision $decision, ?string $comment, Request $request): Decision
    {
        return $this->transition->execute(
            $actor,
            $decision,
            DecisionStatus::Draft,
            $comment,
            AuthorizationSecurityEvent::DECISION_RETURNED_TO_DRAFT,
            $request,
        );
    }
}
