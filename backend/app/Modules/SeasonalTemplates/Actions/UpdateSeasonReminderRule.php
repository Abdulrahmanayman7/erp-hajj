<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRuleGuard;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Edits a `draft` or `paused` rule. Active rules must be paused first and archived
 * rules are immutable. `code` and `season_id` never change; the trigger is re-validated
 * as a whole so a partial PATCH can never leave an inconsistent mode/field pair.
 */
final class UpdateSeasonReminderRule
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data  title?, body?, severity?, trigger_mode?, trigger_on?, offset_days?, recipient_permission?
     */
    public function execute(User $actor, SeasonReminderRule $rule, array $data, Request $request): SeasonReminderRule
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rule, $data, $request, $tenant): SeasonReminderRule {
            $locked = SeasonReminderRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SeasonalTemplatesDomainException::reminderRuleNotEditable();
            }

            if (array_key_exists('code', $data) && trim((string) $data['code']) !== $locked->code) {
                throw SeasonalTemplatesDomainException::reminderRuleCodeImmutable();
            }

            SeasonReminderRuleGuard::assertSeasonOpen(Season::query()->findOrFail($locked->season_id));

            $changes = [];

            foreach (['title'] as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = trim((string) $data[$field]);
                }
            }

            if (array_key_exists('body', $data)) {
                $body = $data['body'] === null ? '' : trim((string) $data['body']);
                $changes['body'] = $body === '' ? null : $body;
            }

            if (array_key_exists('severity', $data)) {
                $changes['severity'] = NotificationSeverity::tryFrom((string) $data['severity'])
                    ?? throw SeasonalTemplatesDomainException::reminderTriggerInvalid('درجة أهمية التذكير غير صالحة.');
            }

            if (array_key_exists('recipient_permission', $data)) {
                $permission = trim((string) $data['recipient_permission']);
                SeasonReminderRecipientPermissions::assertApproved($permission);
                $changes['recipient_permission'] = $permission;
            }

            if (array_intersect(['trigger_mode', 'trigger_on', 'offset_days'], array_keys($data)) !== []) {
                $current = [
                    'trigger_mode' => $locked->trigger_mode->value,
                    'trigger_on' => $locked->trigger_on?->toDateString(),
                    'offset_days' => $locked->offset_days,
                ];
                // A mode switch replaces the whole trigger; otherwise only the sent fields change.
                $merged = array_key_exists('trigger_mode', $data)
                    ? [
                        'trigger_mode' => $data['trigger_mode'],
                        'trigger_on' => $data['trigger_on'] ?? null,
                        'offset_days' => $data['offset_days'] ?? null,
                    ]
                    : array_merge($current, array_intersect_key($data, ['trigger_on' => 1, 'offset_days' => 1]));

                $trigger = SeasonReminderRuleGuard::normalizeTrigger($merged);
                $changes['trigger_mode'] = $trigger['trigger_mode'];
                $changes['trigger_on'] = $trigger['trigger_on'];
                $changes['offset_days'] = $trigger['offset_days'];
            }

            $locked->forceFill($changes);
            $changedFields = array_keys($locked->getDirty());
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_REMINDER_RULE_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_reminder_rule',
                'entity_id' => $locked->id,
                'entity_label' => $locked->code,
                'season_id' => $locked->season_id,
                'status' => $locked->status->value,
                'changed_fields' => $changedFields,
            ], $request);

            return $locked->load(['season', 'createdBy']);
        });
    }
}
