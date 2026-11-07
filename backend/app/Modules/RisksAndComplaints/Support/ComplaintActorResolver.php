<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;

final class ComplaintActorResolver
{
    public function isAssigneeUser(User $actor, OperationalComplaint $complaint): bool
    {
        if ($complaint->assignee_employee_id === null) {
            return false;
        }

        $assignee = $complaint->relationLoaded('assigneeEmployee')
            ? $complaint->assigneeEmployee
            : Employee::query()->whereKey($complaint->assignee_employee_id)->first();

        return $assignee !== null
            && $assignee->user_id !== null
            && (int) $assignee->user_id === (int) $actor->id;
    }

    public function canEscalate(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.escalate')
            || $this->isAssigneeUser($actor, $complaint);
    }
}
