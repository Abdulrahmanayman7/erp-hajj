<?php

namespace App\Modules\Meetings\Policies;

use App\Models\User;
use App\Modules\Meetings\Models\Meeting;

class MeetingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('meetings.view');
    }

    public function view(User $actor, Meeting $meeting): bool
    {
        return $actor->hasPermission('meetings.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $meeting->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('meetings.create');
    }

    public function update(User $actor, Meeting $meeting): bool
    {
        return $actor->hasPermission('meetings.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $meeting->tenant_id;
    }

    public function cancel(User $actor, Meeting $meeting): bool
    {
        return $actor->hasPermission('meetings.cancel')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $meeting->tenant_id;
    }

    public function manageAttendees(User $actor, Meeting $meeting): bool
    {
        return $actor->hasPermission('meetings.manage_attendees')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $meeting->tenant_id;
    }

    public function manageMinutes(User $actor, Meeting $meeting): bool
    {
        return $actor->hasPermission('meetings.manage_minutes')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $meeting->tenant_id;
    }
}
