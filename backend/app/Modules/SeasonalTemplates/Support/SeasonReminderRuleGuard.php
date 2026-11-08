<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Carbon;

/**
 * Domain invariants shared by the rule actions and the safe-copy adapter, so the
 * API and a copy can never produce a rule the other path would reject.
 */
final class SeasonReminderRuleGuard
{
    /** Technical sanity bound for `offset_days` (about one year either side of season start). */
    public const MAX_OFFSET_DAYS = 366;

    public static function assertSeasonOpen(Season $season): void
    {
        if ($season->status === SeasonStatus::Closed) {
            throw SeasonalTemplatesDomainException::reminderSeasonClosed();
        }
    }

    /**
     * Validate the trigger configuration (S08-D13) and return the normalized trio.
     * Fields of the other mode are always cleared.
     *
     * @param  array<string, mixed>  $data
     * @return array{trigger_mode: SeasonReminderTriggerMode, trigger_on: string|null, offset_days: int|null}
     */
    public static function normalizeTrigger(array $data): array
    {
        $mode = SeasonReminderTriggerMode::tryFrom((string) ($data['trigger_mode'] ?? ''));
        if ($mode === null) {
            throw SeasonalTemplatesDomainException::reminderTriggerInvalid();
        }

        $triggerOn = $data['trigger_on'] ?? null;
        $offset = $data['offset_days'] ?? null;

        if ($mode === SeasonReminderTriggerMode::AbsoluteDate) {
            if ($triggerOn === null || $triggerOn === '' || ! self::isDate((string) $triggerOn) || ($offset !== null && $offset !== '')) {
                throw SeasonalTemplatesDomainException::reminderTriggerInvalid('التذكير بتاريخ محدد يتطلب تاريخاً صالحاً ولا يقبل إزاحة بالأيام.');
            }

            return ['trigger_mode' => $mode, 'trigger_on' => (string) $triggerOn, 'offset_days' => null];
        }

        if (
            $offset === null || $offset === '' || filter_var($offset, FILTER_VALIDATE_INT) === false
            || abs((int) $offset) > self::MAX_OFFSET_DAYS
            || ($triggerOn !== null && $triggerOn !== '')
        ) {
            throw SeasonalTemplatesDomainException::reminderTriggerInvalid('التذكير بإزاحة من بداية الموسم يتطلب عدد أيام صحيحاً ولا يقبل تاريخاً محدداً.');
        }

        return ['trigger_mode' => $mode, 'trigger_on' => null, 'offset_days' => (int) $offset];
    }

    /**
     * Whether the rule fires on $today (tenant-local date, Y-m-d). Date-only (S08-D13).
     */
    public static function firesOn(SeasonReminderRule $rule, Season $season, string $today): bool
    {
        if ($rule->trigger_mode === SeasonReminderTriggerMode::AbsoluteDate) {
            return $rule->trigger_on !== null && $rule->trigger_on->toDateString() === $today;
        }

        $start = $season->operational_starts_on;
        if ($rule->offset_days === null || ! $start instanceof Carbon) {
            return false;
        }

        return $start
            ->copy()
            ->startOfDay()
            ->addDays($rule->offset_days)
            ->toDateString() === $today;
    }

    private static function isDate(string $value): bool
    {
        $parsed = Carbon::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->toDateString() === $value;
    }
}
