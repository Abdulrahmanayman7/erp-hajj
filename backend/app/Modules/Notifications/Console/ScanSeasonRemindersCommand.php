<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRuleGuard;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Console\Command;

/**
 * Fires season reminder rules (S08-D15, Controls A and B).
 *
 * Eligibility (Control A) — every condition is required, nothing is inferred:
 *  tenant `active` · season `active` (a draft season is never eligible) · rule `active` ·
 *  fire date equals the tenant-local date · recipient is an active holder of the rule's
 *  (still allowlisted) permission.
 *
 * Idempotency (Control B) — no fire ledger: the notification dedupe key is
 * `SEASON_REMINDER_DUE:season_reminder_rule:{rule.id}:{user.id}:{Y-m-d}` under the
 * unique `(tenant_id, dedupe_key)`. It uses the stable rule id, never the title, body,
 * permission or trigger, so same-day edits and pause/resume cannot re-deliver.
 */
class ScanSeasonRemindersCommand extends Command
{
    protected $signature = 'notifications:scan-season-reminders';

    protected $description = 'Dispatch due season reminder rules (tenant-local date bucket)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
    ): int {
        $count = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $dispatcher, $recipients, &$count): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $dispatcher, $recipients, &$count): void {
                        $timezone = $tenant->timezone ?: 'Asia/Riyadh';
                        $today = now($timezone)->toDateString();

                        $seasons = Season::query()
                            ->where('status', SeasonStatus::Active)
                            ->get()
                            ->keyBy('id');

                        if ($seasons->isEmpty()) {
                            return;
                        }

                        SeasonReminderRule::query()
                            ->where('status', SeasonReminderRuleStatus::Active)
                            ->whereIn('season_id', $seasons->keys()->all())
                            ->orderBy('id')
                            ->chunkById(100, function ($rules) use ($seasons, $today, $dispatcher, $recipients, &$count): void {
                                foreach ($rules as $rule) {
                                    /** @var SeasonReminderRule $rule */
                                    $season = $seasons->get($rule->season_id);
                                    if ($season === null || ! SeasonReminderRuleGuard::firesOn($rule, $season, $today)) {
                                        continue;
                                    }

                                    if (
                                        $rule->recipient_mode !== SeasonReminderRule::RECIPIENT_MODE_PERMISSION_HOLDERS
                                        || ! SeasonReminderRecipientPermissions::isApproved($rule->recipient_permission)
                                    ) {
                                        continue;
                                    }

                                    $holders = $recipients->usersWithPermission($rule->recipient_permission);
                                    if ($holders === []) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::SeasonReminderDue,
                                        recipientUsers: $holders,
                                        title: $rule->title,
                                        body: $rule->body,
                                        severity: $rule->severity,
                                        entityType: 'season_reminder_rule',
                                        entityId: (int) $rule->id,
                                        dedupeBucket: $today,
                                        context: ['title' => $rule->title, 'body' => $rule->body],
                                    );
                                    $count++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned season reminder rules; dispatched {$count} reminder set(s).");

        return self::SUCCESS;
    }
}
