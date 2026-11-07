<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRuleGuard;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creates a reminder rule. Always `draft` with `permission_holders` recipients
 * (S08-D12 / D14); activation is a separate authorized action (Control A).
 * Also used by the safe-copy adapter so a copy follows exactly the same invariants.
 */
final class CreateSeasonReminderRule
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data  code, title, body?, severity, trigger_mode, trigger_on?, offset_days?, recipient_permission
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonReminderRule
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonReminderRule {
            $lockedSeason = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();
            SeasonReminderRuleGuard::assertSeasonOpen($lockedSeason);

            $permission = trim((string) ($data['recipient_permission'] ?? ''));
            SeasonReminderRecipientPermissions::assertApproved($permission);
            $trigger = SeasonReminderRuleGuard::normalizeTrigger($data);
            $severity = NotificationSeverity::tryFrom((string) ($data['severity'] ?? ''))
                ?? throw SeasonalTemplatesDomainException::reminderTriggerInvalid('درجة أهمية التذكير غير صالحة.');

            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '') {
                throw SeasonalTemplatesDomainException::reminderTriggerInvalid('رمز قاعدة التذكير مطلوب.');
            }
            if (SeasonReminderRule::query()->where('season_id', $lockedSeason->id)->where('code', $code)->exists()) {
                throw SeasonalTemplatesDomainException::reminderRuleCodeTaken();
            }

            $rule = new SeasonReminderRule([
                'season_id' => $lockedSeason->id,
                'code' => $code,
                'title' => trim((string) $data['title']),
                'body' => isset($data['body']) && trim((string) $data['body']) !== '' ? trim((string) $data['body']) : null,
                'severity' => $severity,
                'trigger_mode' => $trigger['trigger_mode'],
                'trigger_on' => $trigger['trigger_on'],
                'offset_days' => $trigger['offset_days'],
                'recipient_mode' => SeasonReminderRule::RECIPIENT_MODE_PERMISSION_HOLDERS,
                'recipient_permission' => $permission,
                'created_by' => $actor->id,
            ]);
            $rule->forceFill(['status' => SeasonReminderRuleStatus::Draft]);
            $rule->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_REMINDER_RULE_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_reminder_rule',
                'entity_id' => $rule->id,
                'entity_label' => $rule->code,
                'season_id' => $lockedSeason->id,
                'status' => $rule->status->value,
                'severity' => $rule->severity->value,
                'trigger_mode' => $rule->trigger_mode->value,
                'recipient_permission' => $rule->recipient_permission,
            ], $request);

            return $rule->load(['season', 'createdBy']);
        });
    }
}
