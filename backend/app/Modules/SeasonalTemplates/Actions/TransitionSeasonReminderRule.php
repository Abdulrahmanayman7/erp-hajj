<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRuleGuard;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared atomic lifecycle transition for reminder rules: lock → validate → update → audit.
 *
 * Activation is allowed on `draft` seasons so operators can prepare rules; the scanner
 * (not the rule) enforces that only `active` seasons ever deliver (Control A).
 * Activation is refused for `closed` seasons and when the recipient permission is no
 * longer on the approved allowlist. Pause and archive stay possible on closed seasons.
 */
final class TransitionSeasonReminderRule
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonReminderRule $rule, SeasonReminderRuleStatus $to, Request $request): SeasonReminderRule
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rule, $to, $request, $tenant): SeasonReminderRule {
            $locked = SeasonReminderRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();

            $allowed = match ($to) {
                SeasonReminderRuleStatus::Active => $locked->status->canActivate(),
                SeasonReminderRuleStatus::Paused => $locked->status->canPause(),
                SeasonReminderRuleStatus::Archived => $locked->status->canArchive(),
                SeasonReminderRuleStatus::Draft => false,
            };

            if (! $allowed) {
                throw SeasonalTemplatesDomainException::reminderRuleInvalidTransition();
            }

            if ($to === SeasonReminderRuleStatus::Active) {
                SeasonReminderRuleGuard::assertSeasonOpen(Season::query()->findOrFail($locked->season_id));
                SeasonReminderRecipientPermissions::assertApproved($locked->recipient_permission);
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to])->save();

            $event = match ($to) {
                SeasonReminderRuleStatus::Active => AuthorizationSecurityEvent::SEASON_REMINDER_RULE_ACTIVATED,
                SeasonReminderRuleStatus::Paused => AuthorizationSecurityEvent::SEASON_REMINDER_RULE_PAUSED,
                default => AuthorizationSecurityEvent::SEASON_REMINDER_RULE_ARCHIVED,
            };

            $this->security->record($event, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_reminder_rule',
                'entity_id' => $locked->id,
                'entity_label' => $locked->code,
                'season_id' => $locked->season_id,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $request);

            return $locked->load(['season', 'createdBy']);
        });
    }
}
