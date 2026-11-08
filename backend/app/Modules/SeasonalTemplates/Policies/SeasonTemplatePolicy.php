<?php

namespace App\Modules\SeasonalTemplates\Policies;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;

class SeasonTemplatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('season_templates.view');
    }

    public function view(User $actor, SeasonTemplate $template): bool
    {
        return $actor->hasPermission('season_templates.view') && $this->sameTenant($actor, $template);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('season_templates.manage');
    }

    public function update(User $actor, SeasonTemplate $template): bool
    {
        return $actor->hasPermission('season_templates.manage') && $this->sameTenant($actor, $template);
    }

    public function approve(User $actor, SeasonTemplate $template): bool
    {
        return $actor->hasPermission('season_templates.manage') && $this->sameTenant($actor, $template);
    }

    public function archive(User $actor, SeasonTemplate $template): bool
    {
        return $actor->hasPermission('season_templates.manage') && $this->sameTenant($actor, $template);
    }

    private function sameTenant(User $actor, SeasonTemplate $template): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $template->tenant_id;
    }
}
