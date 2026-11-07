<?php

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationComposer;
use App\Modules\Notifications\Support\NotificationDedupe;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S08 reminder scanner (D15) + Control A (eligibility) + Control B (stable dedupe identity).
 */
function srcToday(?Tenant $tenant = null): string
{
    return now(($tenant?->timezone) ?: 'Asia/Riyadh')->toDateString();
}

function srcTomorrow(?Tenant $tenant = null): string
{
    return now(($tenant?->timezone) ?: 'Asia/Riyadh')->addDay()->toDateString();
}

function srcScan(): void
{
    test()->artisan('notifications:scan-season-reminders')->assertSuccessful();
}

/**
 * @return Collection<int, Notification>
 */
function srcNotifications(Tenant $tenant, ?int $ruleId = null)
{
    return withTenant($tenant, function () use ($ruleId) {
        $query = Notification::query()->where('type', NotificationType::SeasonReminderDue->value)->orderBy('id');
        if ($ruleId !== null) {
            $query->where('entity_id', $ruleId);
        }

        return $query->get();
    });
}

/**
 * @param  array<string, mixed>  $over
 */
function srcActiveRule(Tenant $tenant, Season $season, array $over = [], SeasonReminderRuleStatus $status = SeasonReminderRuleStatus::Active): SeasonReminderRule
{
    return srRule($tenant, $season, array_merge(['trigger_on' => srcToday($tenant)], $over), $status);
}

it('registers the scanner in the daily schedule next to the other notification scans', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $c): bool => str_contains($c, 'notifications:scan-season-reminders')))->toBeTrue()
        ->and($commands->contains(fn (string $c): bool => str_contains($c, 'notifications:scan-contracts')))->toBeTrue();
});

it('fires an active rule of an active season on its tenant-local date to every permission holder', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');
    $rule = srcActiveRule($tenant, $season, ['title' => 'موعد تسليم الخطة', 'body' => 'راجع الخطة التشغيلية.', 'severity' => NotificationSeverity::Critical]);

    srcScan();

    $notifications = srcNotifications($tenant, $rule->id);
    expect($notifications)->toHaveCount(1);

    /** @var Notification $n */
    $n = $notifications->first();
    expect($n->recipient_user_id)->toBe($owner->id)
        ->and($n->title)->toBe('موعد تسليم الخطة')
        ->and($n->body)->toBe('راجع الخطة التشغيلية.')
        ->and($n->severity)->toBe(NotificationSeverity::Critical)
        ->and($n->entity_type)->toBe('season_reminder_rule')
        ->and($n->entity_id)->toBe($rule->id)
        // Control B: the identity is the stable rule id + recipient + tenant-local date bucket.
        ->and($n->dedupe_key)->toBe(NotificationDedupe::buildKey(NotificationType::SeasonReminderDue, 'season_reminder_rule', $rule->id, $owner->id, srcToday($tenant)));
});

it('uses the type default severity and a composer fallback when the rule has no body', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $rule = srcActiveRule($tenant, srSeason($tenant, 'active'), ['severity' => NotificationSeverity::Warning, 'body' => null]);

    srcScan();

    $n = srcNotifications($tenant, $rule->id)->sole();
    expect($n->body)->toBeNull()->and($n->severity)->toBe(NotificationSeverity::Warning);

    expect(NotificationType::SeasonReminderDue->defaultSeverity())->toBe(NotificationSeverity::Warning);
    $composed = app(NotificationComposer::class)
        ->compose(NotificationType::SeasonReminderDue, ['title' => 'عنوان', 'body' => 'نص']);
    expect($composed)->toBe(['title' => 'عنوان', 'body' => 'نص']);
    expect(app(NotificationComposer::class)->compose(NotificationType::SeasonReminderDue)['title'])->toBe('تذكير موسمي');
});

it('computes offset_from_season_start rules from the season operational start (signed, date-only)', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = withTenant($tenant, fn (): Season => Season::factory()->active()->create([
        'operational_starts_on' => now('Asia/Riyadh')->addDays(10)->toDateString(),
        'operational_ends_on' => now('Asia/Riyadh')->addDays(40)->toDateString(),
    ]));

    $before = srRule($tenant, $season, ['code' => 'BEFORE', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => -10], SeasonReminderRuleStatus::Active);
    $later = srRule($tenant, $season, ['code' => 'LATER', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => -9], SeasonReminderRuleStatus::Active);
    $after = srRule($tenant, $season, ['code' => 'AFTER', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 5], SeasonReminderRuleStatus::Active);

    srcScan();

    expect(srcNotifications($tenant, $before->id))->toHaveCount(1)
        ->and(srcNotifications($tenant, $later->id))->toHaveCount(0)
        ->and(srcNotifications($tenant, $after->id))->toHaveCount(0);
});

it('never fires for draft or closed seasons (Control A)', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $draft = srcActiveRule($tenant, srSeason($tenant, 'draft'), ['code' => 'D']);
    $closed = srcActiveRule($tenant, srSeason($tenant, 'closed'), ['code' => 'C']);
    $active = srcActiveRule($tenant, srSeason($tenant, 'active'), ['code' => 'A']);

    srcScan();

    expect(srcNotifications($tenant, $draft->id))->toHaveCount(0)
        ->and(srcNotifications($tenant, $closed->id))->toHaveCount(0)
        ->and(srcNotifications($tenant, $active->id))->toHaveCount(1);
});

it('skips draft, paused and archived rules', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');

    $rules = [];
    foreach (['draft' => SeasonReminderRuleStatus::Draft, 'paused' => SeasonReminderRuleStatus::Paused, 'archived' => SeasonReminderRuleStatus::Archived] as $code => $status) {
        $rules[] = srcActiveRule($tenant, $season, ['code' => $code], $status);
    }

    srcScan();

    expect(srcNotifications($tenant))->toHaveCount(0);
});

it('does not fire on another date and is idempotent within the same day', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');
    $rule = srcActiveRule($tenant, $season, ['code' => 'TODAY']);
    $tomorrow = srcActiveRule($tenant, $season, ['code' => 'TOMORROW', 'trigger_on' => srcTomorrow($tenant)]);
    $yesterday = srcActiveRule($tenant, $season, ['code' => 'YESTERDAY', 'trigger_on' => now('Asia/Riyadh')->subDay()->toDateString()]);

    srcScan();
    srcScan();
    srcScan();

    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1)
        ->and(srcNotifications($tenant, $tomorrow->id))->toHaveCount(0)
        ->and(srcNotifications($tenant, $yesterday->id))->toHaveCount(0);
});

it('does not re-deliver after a same-day title, body, severity, permission or trigger edit (Control B, no ledger)', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');
    $rule = srcActiveRule($tenant, $season, ['title' => 'العنوان الأول']);
    $url = '/api/v1/season-reminder-rules/'.$rule->id;

    srcScan();
    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1);

    // Edits require pausing; the rule id (the dedupe identity) never changes.
    spaPostJson($url.'/pause')->assertOk();
    spaPatchJson($url, ['title' => 'عنوان مختلف تماماً', 'body' => 'نص جديد', 'severity' => 'critical', 'recipient_permission' => 'tasks.view'])->assertOk();
    spaPostJson($url.'/activate')->assertOk();
    srcScan();

    // Trigger moved away and back to today within the same day.
    spaPostJson($url.'/pause')->assertOk();
    spaPatchJson($url, ['trigger_on' => srcTomorrow($tenant)])->assertOk();
    spaPostJson($url.'/activate')->assertOk();
    srcScan();
    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1);

    spaPostJson($url.'/pause')->assertOk();
    spaPatchJson($url, ['trigger_on' => srcToday($tenant)])->assertOk();
    spaPostJson($url.'/activate')->assertOk();
    srcScan();

    $notifications = srcNotifications($tenant, $rule->id);
    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->title)->toBe('العنوان الأول');
});

it('does not re-deliver after pause and resume on the same day', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $rule = srcActiveRule($tenant, srSeason($tenant, 'active'));
    $url = '/api/v1/season-reminder-rules/'.$rule->id;

    srcScan();
    spaPostJson($url.'/pause')->assertOk();
    srcScan();
    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1);

    spaPostJson($url.'/activate')->assertOk();
    srcScan();
    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1);
});

it('does not fire when the trigger is changed to tomorrow before the first scan', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $rule = srcActiveRule($tenant, srSeason($tenant, 'active'), [], SeasonReminderRuleStatus::Paused);
    $url = '/api/v1/season-reminder-rules/'.$rule->id;

    spaPatchJson($url, ['trigger_on' => srcTomorrow($tenant)])->assertOk();
    spaPostJson($url.'/activate')->assertOk();
    srcScan();

    expect(srcNotifications($tenant, $rule->id))->toHaveCount(0);
});

it('delivers again on a later date because the bucket is the tenant-local day', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = withTenant($tenant, fn (): Season => Season::factory()->active()->create([
        'operational_starts_on' => '2027-05-01',
        'operational_ends_on' => '2027-06-30',
    ]));
    $rule = srRule($tenant, $season, ['trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 1], SeasonReminderRuleStatus::Active);
    $second = srRule($tenant, $season, ['code' => 'SECOND', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 2], SeasonReminderRuleStatus::Active);

    Carbon::setTestNow(Carbon::parse('2027-05-02 09:00:00', 'Asia/Riyadh'));
    srcScan();
    srcScan();
    Carbon::setTestNow(Carbon::parse('2027-05-03 09:00:00', 'Asia/Riyadh'));
    srcScan();
    Carbon::setTestNow();

    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1)
        ->and(srcNotifications($tenant, $second->id))->toHaveCount(1);
});

it('uses the tenant timezone, falling back to Asia/Riyadh', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');

    // Pacific/Kiritimati is UTC+14: its calendar date is ahead of Riyadh's for most of the day.
    DB::table('tenants')->where('id', $tenant->id)->update(['timezone' => 'Pacific/Kiritimati']);
    $tenant = Tenant::query()->findOrFail($tenant->id);

    Carbon::setTestNow(Carbon::parse('2027-05-10 21:30:00', 'UTC')); // May 11 11:30 Kiritimati, May 11 00:30 Riyadh
    $rule = srRule($tenant, $season, ['trigger_on' => '2027-05-11'], SeasonReminderRuleStatus::Active);
    $other = srRule($tenant, $season, ['code' => 'RIYADH-ONLY', 'trigger_on' => '2027-05-10'], SeasonReminderRuleStatus::Active);
    srcScan();
    Carbon::setTestNow();

    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1)
        ->and(srcNotifications($tenant, $other->id))->toHaveCount(0);

    // Empty timezone falls back to Asia/Riyadh.
    DB::table('tenants')->where('id', $tenant->id)->update(['timezone' => '']);
    Carbon::setTestNow(Carbon::parse('2027-05-10 21:30:00', 'UTC')); // May 11 in Riyadh
    $fallback = srRule($tenant, $season, ['code' => 'FALLBACK', 'trigger_on' => '2027-05-11'], SeasonReminderRuleStatus::Active);
    srcScan();
    Carbon::setTestNow();

    expect(srcNotifications($tenant, $fallback->id))->toHaveCount(1);
});

it('skips suspended and other non-active tenants entirely', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $rule = srcActiveRule($tenant, srSeason($tenant, 'active'));

    DB::table('tenants')->where('id', $tenant->id)->update(['status' => 'suspended']);
    srcScan();
    DB::table('tenants')->where('id', $tenant->id)->update(['status' => 'active']);

    expect(srcNotifications($tenant, $rule->id))->toHaveCount(0);

    srcScan();
    expect(srcNotifications($tenant, $rule->id))->toHaveCount(1);
});

it('delivers only to active holders of the rule permission within the tenant', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = srSeason($tenant, 'active');

    // Holds season_reminders.view only.
    $viewer = actingAsSeasonalTemplatesUserWith(['season_reminders.view'], $tenant);
    // Holds seasons.view but is disabled.
    $disabled = actingAsSeasonalTemplatesUserWith(['seasons.view', 'season_reminders.view'], $tenant);
    DB::table('users')->where('id', $disabled->id)->update(['status' => UserStatus::Disabled->value]);
    // No relevant permission at all.
    $outsider = actingAsSeasonalTemplatesUserWith(['dashboard.view'], $tenant);

    $viewersRule = srcActiveRule($tenant, $season, ['code' => 'VIEWERS', 'recipient_permission' => 'season_reminders.view']);
    $seasonsRule = srcActiveRule($tenant, $season, ['code' => 'SEASONS', 'recipient_permission' => 'seasons.view']);

    srcScan();

    $viewersRecipients = srcNotifications($tenant, $viewersRule->id)->pluck('recipient_user_id')->sort()->values()->all();
    $seasonsRecipients = srcNotifications($tenant, $seasonsRule->id)->pluck('recipient_user_id')->sort()->values()->all();

    // Helper-provisioned owners hold every permission and are legitimate recipients of both rules.
    expect($viewersRecipients)->toContain($owner->id, $viewer->id)
        ->and($viewersRecipients)->not->toContain($disabled->id)
        ->and($viewersRecipients)->not->toContain($outsider->id)
        ->and($seasonsRecipients)->toContain($owner->id)
        ->and($seasonsRecipients)->not->toContain($viewer->id)
        ->and($seasonsRecipients)->not->toContain($disabled->id)
        ->and($seasonsRecipients)->not->toContain($outsider->id)
        ->and(count($viewersRecipients))->toBe(count(array_unique($viewersRecipients)));
});

it('never delivers a rule whose permission is no longer on the allowlist', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $rule = srcActiveRule($tenant, srSeason($tenant, 'active'));
    DB::table('season_reminder_rules')->where('id', $rule->id)->update(['recipient_permission' => 'users.view']);

    srcScan();

    expect(srcNotifications($tenant))->toHaveCount(0);
});

it('keeps tenants isolated: each tenant only notifies its own users for its own rules', function (): void {
    $owner = actingAsTenantOwner();
    $mine = $owner->tenant;
    $mineRule = srcActiveRule($mine, srSeason($mine, 'active'), ['code' => 'MINE']);

    $foreign = Tenant::factory()->create();
    $foreignOwner = provisionTenantRbac($foreign);
    $foreignRule = srcActiveRule($foreign, srSeason($foreign, 'active'), ['code' => 'THEIRS']);

    srcScan();

    $mineNotifications = srcNotifications($mine);
    $foreignNotifications = srcNotifications($foreign);

    expect($mineNotifications->pluck('entity_id')->all())->toBe([$mineRule->id])
        ->and($mineNotifications->pluck('recipient_user_id')->all())->toBe([$owner->id])
        ->and($foreignNotifications->pluck('entity_id')->all())->toBe([$foreignRule->id])
        ->and($foreignNotifications->pluck('recipient_user_id')->all())->toBe([$foreignOwner->id])
        ->and(DB::table('notifications')->where('type', 'SEASON_REMINDER_DUE')->where('tenant_id', $mine->id)->count())->toBe(1)
        ->and(User::query()->withoutGlobalScopes()->whereKey($owner->id)->value('tenant_id'))->toBe($mine->id);
});
