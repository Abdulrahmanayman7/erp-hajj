<?php

namespace App\Modules\SeasonalTemplates\Policies;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;

class SeasonReminderRulePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('season_reminders.view');
    }

    public function view(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->hasPermission('season_reminders.view') && $this->sameTenant($actor, $rule);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('season_reminders.manage');
    }

    public function update(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->hasPermission('season_reminders.manage') && $this->sameTenant($actor, $rule);
    }

    public function activate(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->hasPermission('season_reminders.manage') && $this->sameTenant($actor, $rule);
    }

    public function pause(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->hasPermission('season_reminders.manage') && $this->sameTenant($actor, $rule);
    }

    public function archive(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->hasPermission('season_reminders.manage') && $this->sameTenant($actor, $rule);
    }

    private function sameTenant(User $actor, SeasonReminderRule $rule): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $rule->tenant_id;
    }
}
