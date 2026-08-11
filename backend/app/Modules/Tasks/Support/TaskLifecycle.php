<?php

namespace App\Modules\Tasks\Support;

use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;

final class TaskLifecycle
{
    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            TaskStatus::Draft->value => [
                TaskStatus::Assigned->value,
                TaskStatus::Cancelled->value,
            ],
            TaskStatus::Assigned->value => [
                TaskStatus::InProgress->value,
                TaskStatus::Cancelled->value,
            ],
            TaskStatus::InProgress->value => [
                TaskStatus::Completed->value,
                TaskStatus::Cancelled->value,
            ],
        ];
    }

    public static function assertAllowed(TaskStatus $from, TaskStatus $to): void
    {
        $allowed = self::allowedTransitions()[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw TaskDomainException::invalidStatusTransition();
        }
    }

    public static function requiresComment(TaskStatus $from, TaskStatus $to): bool
    {
        return $to === TaskStatus::Cancelled;
    }
}
