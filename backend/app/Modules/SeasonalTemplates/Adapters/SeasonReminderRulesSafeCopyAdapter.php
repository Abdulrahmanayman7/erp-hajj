<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Actions\CreateSeasonReminderRule;
use App\Modules\SeasonalTemplates\Actions\UpdateSeasonReminderRule;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyContext;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonPilgrims\Models\Season;

/**
 * season_reminder_rules (S08-D16): code, title, body, severity, trigger_mode, trigger_on
 * (remapped by the season-start delta), offset_days (kept as is — it is already relative
 * to the target season start), recipient_mode, recipient_permission.
 *
 * Target rules are always created through {@see CreateSeasonReminderRule}: status is forced
 * to `draft` and recipients to `permission_holders`; the source status is never read, so a
 * copy can never start delivering (Control A). Users / employee ids do not exist on a rule
 * and are never copied. Archived source rules are not copied. A target rule that is
 * `active` or `archived` is locked (changes become TARGET_LOCKED conflicts).
 */
final class SeasonReminderRulesSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function __construct(
        SafeCopyProvenanceStore $provenance,
        private readonly CreateSeasonReminderRule $create,
        private readonly UpdateSeasonReminderRule $update,
    ) {
        parent::__construct($provenance);
    }

    public function entityKind(): string
    {
        return SafeCopyAllowlist::SEASON_REMINDER_RULES;
    }

    protected function requiredDateFields(): array
    {
        return ['trigger_on'];
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        $rules = SeasonReminderRule::query()
            ->where('season_id', $source->id)
            ->where('status', '!=', SeasonReminderRuleStatus::Archived->value)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $rows[$rule->code] = ['source_id' => $rule->id, 'fields' => $this->fieldsOf($rule)];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        $rows = [];
        $rules = SeasonReminderRule::query()->where('season_id', $target->id)->orderBy('id')->get();

        foreach ($rules as $rule) {
            $rows[$rule->code] = [
                'id' => $rule->id,
                'fields' => $this->fieldsOf($rule),
                'locked' => ! $rule->status->isEditable(),
            ];
        }

        return $rows;
    }

    /**
     * A recipient permission that is not on the approved allowlist (e.g. the allowlist
     * changed after a template was approved) is a missing dependency: the row is never
     * created, the operator can only skip it.
     */
    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        $permission = (string) $fields['recipient_permission'];
        if (! SeasonReminderRecipientPermissions::isApproved($permission)) {
            return ['dependency' => 'recipient_permission', 'recipient_permission' => mb_substr($permission, 0, 64)];
        }

        return null;
    }

    protected function insertRow(SafeCopyContext $context, array $fields, User $actor): ?int
    {
        $rule = $this->create->execute($actor, $context->target, $this->payload($fields) + ['code' => $fields['code']], $context->request);

        return $rule->id;
    }

    protected function updateRow(SafeCopyContext $context, int $targetId, array $fields, User $actor): void
    {
        $this->update->execute($actor, SeasonReminderRule::query()->findOrFail($targetId), $this->payload($fields), $context->request);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function payload(array $fields): array
    {
        $absolute = $fields['trigger_mode'] === SeasonReminderTriggerMode::AbsoluteDate->value;

        return [
            'title' => $fields['title'],
            'body' => $fields['body'],
            'severity' => $fields['severity'],
            'trigger_mode' => $fields['trigger_mode'],
            'trigger_on' => $absolute ? $fields['trigger_on'] : null,
            'offset_days' => $absolute ? null : $fields['offset_days'],
            'recipient_permission' => $fields['recipient_permission'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(SeasonReminderRule $rule): array
    {
        return $this->allowlisted([
            'code' => $rule->code,
            'title' => $rule->title,
            'body' => $rule->body,
            'severity' => $rule->severity->value,
            'trigger_mode' => $rule->trigger_mode->value,
            'trigger_on' => $rule->trigger_on?->toDateString(),
            'offset_days' => $rule->offset_days,
            'recipient_mode' => $rule->recipient_mode,
            'recipient_permission' => $rule->recipient_permission,
        ]);
    }
}
