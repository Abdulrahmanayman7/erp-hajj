<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use Illuminate\Http\Request;

final class ArchiveSeasonReminderRule
{
    public function __construct(private readonly TransitionSeasonReminderRule $transition) {}

    /**
     * active|paused → archived (terminal).
     */
    public function execute(User $actor, SeasonReminderRule $rule, Request $request): SeasonReminderRule
    {
        return $this->transition->execute($actor, $rule, SeasonReminderRuleStatus::Archived, $request);
    }
}
