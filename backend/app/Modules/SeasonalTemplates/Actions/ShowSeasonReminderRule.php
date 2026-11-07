<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;

final class ShowSeasonReminderRule
{
    public function execute(SeasonReminderRule $rule): SeasonReminderRule
    {
        return $rule->load(['season', 'createdBy']);
    }
}
