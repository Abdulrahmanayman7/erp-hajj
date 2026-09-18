<?php

namespace App\Modules\Settings\Policies;

use App\Models\User;

class TenantSettingsPolicy
{
    public function view(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('tenant_settings.view');
    }

    public function update(User $actor): bool
    {
        return $actor->tenant_id !== null
            && $actor->isActive()
            && $actor->hasPermission('tenant_settings.update');
    }
}
