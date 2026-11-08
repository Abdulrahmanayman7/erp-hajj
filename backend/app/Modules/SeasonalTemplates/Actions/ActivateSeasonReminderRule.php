<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use Illuminate\Http\Request;

final class ActivateSeasonReminderRule
{
    public function __construct(private readonly TransitionSeasonReminderRule $transition) {}

    /**
     * draft|paused → active.
     */
    public function execute(User $actor, SeasonReminderRule $rule, Request $request): SeasonReminderRule
    {
        return $this->transition->execute($actor, $rule, SeasonReminderRuleStatus::Active, $request);
    }
}
