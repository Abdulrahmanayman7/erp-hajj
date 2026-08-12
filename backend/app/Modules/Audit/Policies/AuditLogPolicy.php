<?php

namespace App\Modules\Audit\Policies;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;

final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit_logs.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $user->hasPermission('audit_logs.view')) {
            return false;
        }

        if ($user->tenant_id === null) {
            return false;
        }

        return (int) $auditLog->tenant_id === (int) $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
