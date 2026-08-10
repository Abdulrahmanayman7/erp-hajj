<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Exceptions\DecisionDomainException;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;

final class CloseDecision
{
    public function __construct(
        private readonly TransitionDecision $transition,
    ) {}

    public function execute(User $actor, Decision $decision, ?string $comment, Request $request): Decision
    {
        $openStatuses = array_map(
            static fn (TaskStatus $status): string => $status->value,
            TaskStatus::openStatuses(),
        );

        $hasOpenTasks = Task::query()
            ->where('decision_id', $decision->id)
            ->whereIn('status', $openStatuses)
            ->exists();

        if ($hasOpenTasks) {
            throw DecisionDomainException::closeNotAllowed();
        }

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
