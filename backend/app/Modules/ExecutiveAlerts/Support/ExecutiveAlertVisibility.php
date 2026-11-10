<?php

namespace App\Modules\ExecutiveAlerts\Support;

use App\Models\User;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the per-rule domain permission (S10-D02 = C "domain omit") on top of the
 * tenant scope. Alerts of a domain the actor cannot view are neither listed,
 * counted nor acknowledgeable (they answer 404 like any hidden row).
 */
final class ExecutiveAlertVisibility
{
    /**
     * @return list<string>
     */
    public static function visibleRuleCodes(User $actor): array
    {
        $codes = [];
        foreach (ExecutiveAlertRule::cases() as $rule) {
            if ($actor->hasPermission($rule->domainPermission())) {
                $codes[] = $rule->value;
            }
        }

        return $codes;
    }

    /**
     * @return Builder<ExecutiveAlert>
     */
    public static function query(User $actor): Builder
    {
        return ExecutiveAlert::query()->whereIn('rule_code', self::visibleRuleCodes($actor));
    }
}
