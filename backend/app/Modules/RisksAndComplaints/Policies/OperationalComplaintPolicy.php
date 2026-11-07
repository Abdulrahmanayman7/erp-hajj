<?php

namespace App\Modules\RisksAndComplaints\Policies;

use App\Models\User;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\ComplaintActorResolver;

class OperationalComplaintPolicy
{
    public function __construct(
        private readonly ComplaintActorResolver $actors,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('complaints.view');
    }

    public function view(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.view') && $this->sameTenant($actor, $complaint);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('complaints.create');
    }

    public function update(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.update') && $this->sameTenant($actor, $complaint);
    }

    public function assign(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.assign') && $this->sameTenant($actor, $complaint);
    }

    public function escalate(User $actor, OperationalComplaint $complaint): bool
    {
        if (! $this->sameTenant($actor, $complaint)) {
            return false;
        }

        return $actor->hasPermission('complaints.escalate')
            || $this->actors->isAssigneeUser($actor, $complaint);
    }

    public function resolve(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.resolve') && $this->sameTenant($actor, $complaint);
    }

    public function close(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.close') && $this->sameTenant($actor, $complaint);
    }

    public function reopen(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.reopen') && $this->sameTenant($actor, $complaint);
    }

    public function addNote(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.add_note') && $this->sameTenant($actor, $complaint);
    }

    public function delete(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->hasPermission('complaints.delete') && $this->sameTenant($actor, $complaint);
    }

    private function sameTenant(User $actor, OperationalComplaint $complaint): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $complaint->tenant_id;
    }
}
