<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use Illuminate\Http\Request;

final class CloseDecision
{
    public function __construct(
        private readonly TransitionDecision $transition,
    ) {}

    public function execute(User $actor, Decision $decision, ?string $comment, Request $request): Decision
    {
        return $this->transition->execute(
            $actor,
            $decision,
            DecisionStatus::Closed,
            $comment,
            AuthorizationSecurityEvent::DECISION_CLOSED,
            $request,
        );
    }
}
