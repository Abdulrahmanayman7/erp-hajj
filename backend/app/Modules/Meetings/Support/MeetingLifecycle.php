<?php

namespace App\Modules\Meetings\Support;

use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;

/**
 * Centralized meeting lifecycle transition map.
 *
 * @see docs/09-modules/06-meetings/BUSINESS_RULES.md
 */
final class MeetingLifecycle
{
    /**
     * Allowed edges: from => list of to statuses.
     *
     * Reschedule (scheduled → scheduled) is handled separately and does not
     * appear here as a status change edge.
     *
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            MeetingStatus::Draft->value => [
                MeetingStatus::Scheduled->value,
                MeetingStatus::Cancelled->value,
            ],
            MeetingStatus::Scheduled->value => [
                MeetingStatus::InProgress->value,
                MeetingStatus::Cancelled->value,
            ],
            MeetingStatus::InProgress->value => [
                MeetingStatus::Completed->value,
                MeetingStatus::Cancelled->value,
            ],
        ];
    }

    public static function assertAllowed(MeetingStatus $from, MeetingStatus $to): void
    {
        $allowed = self::allowedTransitions()[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw MeetingDomainException::invalidStatusTransition();
        }
    }

    public static function requiresComment(MeetingStatus $from, MeetingStatus $to): bool
    {
        return $to === MeetingStatus::Cancelled;
    }
}
