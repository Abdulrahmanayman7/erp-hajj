<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimOperationalReferenceChecker;
use App\Modules\SeasonPilgrims\Events\SeasonPilgrimCancelled;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Support\SeasonPilgrimOperationalReferences;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;

it('registers a pilgrim without a group and generates a reference number', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();

    $sp = spRegister($season['id'], $pilgrim['id']);

    expect($sp['status'])->toBe('registered')
        ->and($sp['season_id'])->toBe($season['id'])
        ->and($sp['pilgrim_group'])->toBeNull()
        ->and($sp['reference_number'])->toBe('SP-000001')
        ->and($sp['identity_snapshot']['is_locked'])->toBeFalse()
        ->and($sp['identity_snapshot']['full_name'])->toBe($pilgrim['full_name']);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_REGISTERED)->exists())->toBeTrue();
});

it('generates unique sequential reference numbers per season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();

    $numbers = collect(range(1, 3))
        ->map(fn () => spRegister($season['id'], spCreatePilgrim()['id'])['reference_number'])
        ->all();

    expect($numbers)->toBe(['SP-000001', 'SP-000002', 'SP-000003']);
});

it('rejects a duplicate participation of the same pilgrim in the same season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();
    spRegister($season['id'], $pilgrim['id']);

    spaPostJson('/api/v1/season-pilgrims', ['pilgrim_id' => $pilgrim['id']], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_ALREADY_REGISTERED');
});

it('enforces participation and reference uniqueness at the database level', function (): void {
    $owner = actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();
    $sp = spRegister($season['id'], $pilgrim['id']);
    $other = spCreatePilgrim(['full_name' => 'آخر']);

    withTenant($owner->tenant, function () use ($season, $pilgrim, $other, $sp): void {
        $base = ['season_id' => $season['id'], 'status' => 'registered', 'registered_at' => now()];

        expect(fn () => SeasonPilgrim::query()->create($base + ['pilgrim_id' => $pilgrim['id'], 'reference_number' => 'SP-X']))
            ->toThrow(UniqueConstraintViolationException::class);
        expect(fn () => SeasonPilgrim::query()->create($base + ['pilgrim_id' => $other['id'], 'reference_number' => $sp['reference_number']]))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('allows the same pilgrim to participate in different seasons', function (): void {
    actingAsTenantOwner();
    $draft = spCreateSeason();
    $active = spActiveSeason();
    $pilgrim = spCreatePilgrim();

    spRegister($active['id'], $pilgrim['id']);
    $second = spRegister($draft['id'], $pilgrim['id']);

    expect($second['reference_number'])->toBe('SP-000001');
});

it('requires a pilgrim group to activate a participation', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);

    $response = spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_ACTIVATION_REQUIREMENTS_NOT_MET');

    expect($response->json('errors.requirements'))->toContain('PILGRIM_GROUP_REQUIRED');
});

it('requires an Active season to activate a participation', function (): void {
    actingAsTenantOwner();
    $draft = spCreateSeason();
    $group = spCreateGroup($draft['id']);
    $sp = spRegister($draft['id'], spCreatePilgrim()['id'], ['season_pilgrim_group_id' => $group['id']]);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($draft['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_NOT_ACTIVE');
});

it('activates a participation, locks the snapshot and audits both', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();

    $data = spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))
        ->assertOk()
        ->json('data');

    expect($data['status'])->toBe('active')
        ->and($data['identity_snapshot']['is_locked'])->toBeTrue();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_ACTIVATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_LOCKED)->exists())->toBeTrue();
});

it('locks the pilgrim group after activation', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    $other = spCreateGroup($season['id'], 'G2');

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => $other['id']], seasonHeader($season['id']))
        ->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => $sp['pilgrim_group']['id']], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_LOCKED');
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => null], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_LOCKED');
});

it('refreshes the snapshot while Registered but never after activation', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'pilgrim' => $pilgrim, 'sp' => $sp] = spReadyParticipation();
    $doc = spAddDocument($pilgrim['id'], 'R1000001');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/refresh", ['pilgrim_identity_document_id' => $doc['id']], seasonHeader($season['id']))
        ->assertOk()
        ->assertJsonPath('data.identity_snapshot.document_number', 'R1000001');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPatchJson("/api/v1/pilgrims/{$pilgrim['id']}", ['full_name' => 'اسم بعد التفعيل'])->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/refresh", [], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_SNAPSHOT_LOCKED');

    spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertJsonPath('data.identity_snapshot.full_name', $pilgrim['full_name']);
});

it('corrects a locked snapshot with permission and reason, audited, without touching the pilgrim', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'pilgrim' => $pilgrim, 'sp' => $sp] = spReadyParticipation();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", ['reason' => 'قبل القفل', 'full_name' => 'x'], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PILGRIM_SNAPSHOT_NOT_LOCKED');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", ['full_name' => 'x'], seasonHeader($season['id']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", [
        'reason' => 'خطأ إملائي في الاسم',
        'full_name' => 'الاسم المصحح',
    ], seasonHeader($season['id']))
        ->assertOk()
        ->assertJsonPath('data.identity_snapshot.full_name', 'الاسم المصحح')
        ->assertJsonPath('data.identity_snapshot.correction_count', 1)
        ->assertJsonPath('data.identity_snapshot.is_locked', true);

    spaGetJson("/api/v1/pilgrims/{$pilgrim['id']}")->assertJsonPath('data.full_name', $pilgrim['full_name']);

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_CORRECTED)->first();
    expect($row)->not->toBeNull()
        ->and($row->reason)->toBe('خطأ إملائي في الاسم')
        ->and($row->entity_type)->toBe('season_pilgrim')
        ->and($row->after_values['full_name'] ?? null)->toBe('الاسم المصحح')
        ->and($row->before_values['full_name'] ?? null)->toBe($pilgrim['full_name']);
});

it('requires the correct_snapshot permission for corrections', function (): void {
    actingAsSeasonUserWith(seasonPilgrimsPermissionsExcept(['season_pilgrims.correct_snapshot']));
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", [
        'reason' => 'تصحيح', 'full_name' => 'y',
    ], seasonHeader($season['id']))->assertForbidden();
});

it('completes an Active participation only', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/complete", [], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_INVALID_TRANSITION');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/complete", [], seasonHeader($season['id']))
        ->assertOk()->assertJsonPath('data.status', 'completed');
});

it('cancels with a reason, preserves history and dispatches the cancellation event', function (): void {
    Event::fake([SeasonPilgrimCancelled::class]);
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", [], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonValidationErrors(['reason']);

    $data = spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'اعتذار الحاج'], seasonHeader($season['id']))
        ->assertOk()
        ->json('data');

    expect($data['status'])->toBe('cancelled')
        ->and($data['cancellation_reason'])->toBe('اعتذار الحاج')
        ->and($data['activated_at'])->not->toBeNull()
        ->and($data['reference_number'])->toBe($sp['reference_number'])
        ->and($data['identity_snapshot']['is_locked'])->toBeTrue()
        ->and($data['pilgrim_group']['id'])->toBe($sp['pilgrim_group']['id']);

    Event::assertDispatched(SeasonPilgrimCancelled::class, function (SeasonPilgrimCancelled $event) use ($sp, $season): bool {
        return $event->seasonPilgrimId === $sp['id']
            && $event->seasonId === $season['id']
            && $event->previousStatus === 'active'
            && $event->reason === 'اعتذار الحاج';
    });

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_CANCELLED)->first();
    expect($row)->not->toBeNull()->and($row->reason)->toBe('اعتذار الحاج');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'مرة أخرى'], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_TERMINAL');
});

it('deletes a Registered participation that has no operational reference', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);

    spaDeleteJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))->assertOk();

    expect(SeasonPilgrim::query()->withoutGlobalScopes()->find($sp['id']))->toBeNull()
        ->and(SeasonPilgrimIdentitySnapshot::query()->withoutGlobalScopes()->where('season_pilgrim_id', $sp['id'])->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_DELETED)->exists())->toBeTrue();
});

it('forbids deleting a participation after it was activated, even when cancelled', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaDeleteJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_DELETE_FORBIDDEN');

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'إلغاء'], seasonHeader($season['id']))->assertOk();
    spaDeleteJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_DELETE_FORBIDDEN');
});

it('forbids deleting a participation referenced by a future operational module', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);

    app()->bind('test.operational-checker', fn () => new class implements SeasonPilgrimOperationalReferenceChecker
    {
        public function isReferenced(SeasonPilgrim $seasonPilgrim): bool
        {
            return true;
        }
    });
    app()->tag(['test.operational-checker'], SeasonPilgrimOperationalReferences::CHECKER_TAG);

    spaDeleteJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_DELETE_FORBIDDEN');
});

it('blocks all participation writes in a Closed season but keeps it readable', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    $late = spCreatePilgrim(['full_name' => 'متأخر']);
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    $header = seasonHeader($season['id']);
    spaGetJson('/api/v1/season-pilgrims', $header)->assertOk()->assertJsonCount(1, 'data');
    spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", $header)->assertOk();

    spaPostJson('/api/v1/season-pilgrims', ['pilgrim_id' => $late['id']], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'بعد الإغلاق'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaDeleteJson("/api/v1/season-pilgrims/{$sp['id']}", $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
});

/**
 * Active participation with a locked snapshot in a season that is then closed.
 *
 * @return array{season: array<string, mixed>, pilgrim: array<string, mixed>, sp: array<string, mixed>}
 */
function spClosedSeasonWithLockedSnapshot(): array
{
    ['season' => $season, 'pilgrim' => $pilgrim, 'sp' => $sp] = spReadyParticipation();
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    return compact('season', 'pilgrim', 'sp');
}

it('allows the exceptional snapshot correction on a Closed season with permission, reason and audit', function (): void {
    $actor = actingAsTenantOwner();
    ['season' => $season, 'pilgrim' => $pilgrim, 'sp' => $sp] = spClosedSeasonWithLockedSnapshot();
    $header = seasonHeader($season['id']);

    spaPatchJson("/api/v1/pilgrims/{$pilgrim['id']}", ['full_name' => 'اسم حالي جديد', 'nationality' => 'EG'])->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", [
        'reason' => 'تصحيح تاريخي بعد الإغلاق',
        'gender' => 'female',
    ], $header)
        ->assertOk()
        ->assertJsonPath('data.identity_snapshot.gender', 'female')
        ->assertJsonPath('data.identity_snapshot.full_name', $pilgrim['full_name'])
        ->assertJsonPath('data.identity_snapshot.nationality', 'SA')
        ->assertJsonPath('data.identity_snapshot.is_locked', true)
        ->assertJsonPath('data.identity_snapshot.correction_count', 1);

    spaGetJson("/api/v1/seasons/{$season['id']}")->assertJsonPath('data.status', 'closed');

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_CORRECTED)->sole();
    expect($row->actor_user_id)->toBe($actor->id)
        ->and($row->reason)->toBe('تصحيح تاريخي بعد الإغلاق')
        ->and($row->entity_type)->toBe('season_pilgrim')
        ->and($row->entity_id)->toBe($sp['id'])
        ->and($row->metadata['season_status'] ?? null)->toBe('closed')
        ->and($row->metadata['season_pilgrim_status'] ?? null)->toBe('active')
        ->and($row->before_values['gender'] ?? null)->toBe('male')
        ->and($row->after_values['gender'] ?? null)->toBe('female')
        ->and($row->correlation_id)->not->toBeEmpty();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_REOPENED)->exists())->toBeFalse();
});

it('requires a non-empty reason for a Closed-season snapshot correction', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spClosedSeasonWithLockedSnapshot();

    foreach ([[], ['reason' => ''], ['reason' => '   ']] as $payload) {
        spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", $payload + ['gender' => 'female'], seasonHeader($season['id']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
});

it('requires the correct_snapshot permission on a Closed season too', function (): void {
    actingAsSeasonUserWith(seasonPilgrimsPermissionsExcept(['season_pilgrims.correct_snapshot']));
    ['season' => $season, 'sp' => $sp] = spClosedSeasonWithLockedSnapshot();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", [
        'reason' => 'محاولة بدون صلاحية', 'gender' => 'female',
    ], seasonHeader($season['id']))->assertForbidden();
});

it('keeps every other write blocked on a Closed season after a correction', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spClosedSeasonWithLockedSnapshot();
    $header = seasonHeader($season['id']);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/correct", ['reason' => 'تصحيح', 'gender' => 'female'], $header)->assertOk();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/snapshot/refresh", [], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/complete", [], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'بعد الإغلاق'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => null], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => null], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPatchJson("/api/v1/seasons/{$season['id']}", ['name' => 'تعديل'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');

    spaGetJson("/api/v1/seasons/{$season['id']}")->assertJsonPath('data.status', 'closed');
});

it('filters season pilgrims by status and group', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'group' => $group, 'sp' => $sp] = spReadyParticipation();
    spRegister($season['id'], spCreatePilgrim(['full_name' => 'بدون مجموعة'])['id']);
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaGetJson('/api/v1/season-pilgrims?status=active', seasonHeader($season['id']))->assertOk()->assertJsonCount(1, 'data');
    spaGetJson("/api/v1/season-pilgrims?season_pilgrim_group_id={$group['id']}", seasonHeader($season['id']))
        ->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/season-pilgrims', seasonHeader($season['id']))->assertOk()->assertJsonCount(2, 'data');
});
