<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function createMeetingViaApi(array $attrs = []): array
{
    return spaPostJson('/api/v1/meetings', array_merge([
        'title' => 'اجتماع اختبار',
    ], $attrs))->assertCreated()->json('data');
}

/**
 * Walk draft → scheduled → in_progress as needed.
 *
 * @return array<string, mixed>
 */
function advanceMeetingTo(int|string $meetingId, string $status, ?string $scheduledAt = null): array
{
    $order = [
        MeetingStatus::Draft->value,
        MeetingStatus::Scheduled->value,
        MeetingStatus::InProgress->value,
        MeetingStatus::Completed->value,
    ];

    $targetIndex = array_search($status, $order, true);
    expect($targetIndex)->not->toBeFalse();

    $current = spaGetJson("/api/v1/meetings/{$meetingId}")->assertOk()->json('data');
    $currentIndex = array_search($current['status'], $order, true);
    expect($currentIndex)->not->toBeFalse();

    for ($i = $currentIndex + 1; $i <= $targetIndex; $i++) {
        $step = $order[$i];

        if ($step === MeetingStatus::Scheduled->value) {
            $current = spaPostJson("/api/v1/meetings/{$meetingId}/schedule", [
                'scheduled_at' => $scheduledAt ?? now()->addDay()->toIso8601String(),
            ])->assertOk()->assertJsonPath('data.status', $step)->json('data');
        } elseif ($step === MeetingStatus::InProgress->value) {
            $current = spaPostJson("/api/v1/meetings/{$meetingId}/start")
                ->assertOk()
                ->assertJsonPath('data.status', $step)
                ->json('data');
        } elseif ($step === MeetingStatus::Completed->value) {
            spaPutJson("/api/v1/meetings/{$meetingId}/minutes", [
                'minutes_body' => 'محضر الاجتماع',
            ])->assertOk();

            $current = spaPostJson("/api/v1/meetings/{$meetingId}/complete")
                ->assertOk()
                ->assertJsonPath('data.status', $step)
                ->json('data');
        }
    }

    return $current;
}

test('M01 create generates MTG-000001 ignores client fields and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $data = spaPostJson('/api/v1/meetings', [
        'title' => 'اجتماع إنشاء',
        'meeting_number' => 'HACK-9',
        'status' => 'completed',
        'tenant_id' => 999999,
        'minutes_body' => 'should ignore',
    ])->assertCreated()
        ->assertJsonPath('data.meeting_number', 'MTG-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'اجتماع إنشاء')
        ->json('data');

    $shown = spaGetJson("/api/v1/meetings/{$data['id']}")->assertOk()->json('data');
    expect($shown['transitions'])->toHaveCount(1)
        ->and($shown['transitions'][0]['from_status'])->toBeNull()
        ->and($shown['transitions'][0]['to_status'])->toBe('draft')
        ->and($shown['minutes_body'])->toBeNull();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MEETING_CREATED,
    );
});

test('M02 sequential numbers and per-tenant independent sequences', function (): void {
    actingAsTenantOwner();

    $numbers = [];
    for ($i = 0; $i < 5; $i++) {
        $numbers[] = spaPostJson('/api/v1/meetings', [
            'title' => "اجتماع {$i}",
        ])->assertCreated()->json('data.meeting_number');
    }

    expect($numbers)->toBe(['MTG-000001', 'MTG-000002', 'MTG-000003', 'MTG-000004', 'MTG-000005']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaPostJson('/api/v1/meetings', [
        'title' => 'اجتماع B',
    ])->assertCreated()->assertJsonPath('data.meeting_number', 'MTG-000001');

    expect(
        Meeting::withoutGlobalScopes()->where('meeting_number', 'MTG-000001')->count()
    )->toBe(2);
});

test('M03 lifecycle happy path schedule start complete with minutes and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $meeting = createMeetingViaApi(['title' => 'مسار كامل']);

    advanceMeetingTo($meeting['id'], MeetingStatus::Completed->value);

    $shown = spaGetJson("/api/v1/meetings/{$meeting['id']}")->assertOk()->json('data');
    expect($shown['status'])->toBe('completed')
        ->and($shown['started_at'])->not->toBeNull()
        ->and($shown['ended_at'])->not->toBeNull()
        ->and($shown['transitions'])->toHaveCount(4);

    $statuses = array_column($shown['transitions'], 'to_status');
    expect($statuses)->toBe(['draft', 'scheduled', 'in_progress', 'completed']);

    foreach ([
        AuthorizationSecurityEvent::MEETING_CREATED,
        AuthorizationSecurityEvent::MEETING_SCHEDULED,
        AuthorizationSecurityEvent::MEETING_STARTED,
        AuthorizationSecurityEvent::MEETING_MINUTES_UPDATED,
        AuthorizationSecurityEvent::MEETING_COMPLETED,
    ] as $event) {
        Event::assertDispatched(
            AuthorizationSecurityEvent::class,
            fn (AuthorizationSecurityEvent $e): bool => $e->name === $event,
        );
    }
});

test('M04 cancel requires comment; complete requires minutes', function (): void {
    actingAsTenantOwner();
    $meeting = createMeetingViaApi();

    spaPostJson("/api/v1/meetings/{$meeting['id']}/cancel")
        ->assertStatus(422);

    spaPostJson("/api/v1/meetings/{$meeting['id']}/cancel", [
        'comment' => 'ألغي الاجتماع',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    $other = createMeetingViaApi(['title' => 'بدون محضر']);
    advanceMeetingTo($other['id'], MeetingStatus::InProgress->value);

    spaPostJson("/api/v1/meetings/{$other['id']}/complete")
        ->assertStatus(422)
        ->assertJsonPath('code', 'MEETING_MINUTES_REQUIRED');
});

test('M05 complete finalizes draft recommendations', function (): void {
    actingAsTenantOwner();
    $meeting = createMeetingViaApi(['title' => 'توصيات']);
    advanceMeetingTo($meeting['id'], MeetingStatus::InProgress->value);

    $rec = spaPostJson("/api/v1/meetings/{$meeting['id']}/recommendations", [
        'title' => 'توصية مسودة',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->json('data');

    spaPutJson("/api/v1/meetings/{$meeting['id']}/minutes", [
        'minutes_body' => 'محضر مع توصيات',
    ])->assertOk();

    spaPostJson("/api/v1/meetings/{$meeting['id']}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $shown = spaGetJson("/api/v1/meetings/{$meeting['id']}")->assertOk()->json('data');
    $found = collect($shown['recommendations'])->firstWhere('id', $rec['id']);
    expect($found)->not->toBeNull()
        ->and($found['status'])->toBe('final');
});

test('M06 attendees duplicate blocked; attendance update', function (): void {
    actingAsTenantOwner();
    $owner = auth()->user();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);
    $employee = withTenant($tenant, fn () => Employee::factory()->create());

    $meeting = createMeetingViaApi();

    spaPostJson("/api/v1/meetings/{$meeting['id']}/attendees", [
        'employee_id' => $employee->id,
    ])->assertCreated()->assertJsonPath('data.attendance_status', 'invited');

    spaPostJson("/api/v1/meetings/{$meeting['id']}/attendees", [
        'employee_id' => $employee->id,
    ])->assertStatus(422)->assertJsonPath('code', 'MEETING_ATTENDEE_DUPLICATE');

    $attendees = spaGetJson("/api/v1/meetings/{$meeting['id']}/attendees")->assertOk()->json('data');
    $attendeeId = $attendees[0]['id'];

    spaPatchJson("/api/v1/meetings/{$meeting['id']}/attendees/{$attendeeId}", [
        'attendance_status' => 'attended',
    ])->assertOk()->assertJsonPath('data.attendance_status', 'attended');
});

test('M07 delete draft OK; after schedule forbidden', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $draft = createMeetingViaApi(['title' => 'للحذف']);
    spaDeleteJson("/api/v1/meetings/{$draft['id']}")->assertOk();
    spaGetJson("/api/v1/meetings/{$draft['id']}")->assertNotFound();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MEETING_DELETED,
    );

    $scheduled = createMeetingViaApi(['title' => 'مجدول']);
    advanceMeetingTo($scheduled['id'], MeetingStatus::Scheduled->value);
    spaDeleteJson("/api/v1/meetings/{$scheduled['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'MEETING_DELETE_FORBIDDEN');
});

test('M08 reschedule only from scheduled with history', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $meeting = createMeetingViaApi();

    spaPostJson("/api/v1/meetings/{$meeting['id']}/reschedule", [
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
    ])->assertStatus(422)->assertJsonPath('code', 'MEETING_INVALID_STATUS_TRANSITION');

    advanceMeetingTo($meeting['id'], MeetingStatus::Scheduled->value, now()->addDay()->toIso8601String());

    $newTime = now()->addDays(3)->toIso8601String();
    spaPostJson("/api/v1/meetings/{$meeting['id']}/reschedule", [
        'scheduled_at' => $newTime,
        'comment' => 'تأجيل',
    ])->assertOk()->assertJsonPath('data.status', 'scheduled');

    $shown = spaGetJson("/api/v1/meetings/{$meeting['id']}")->assertOk()->json('data');
    $rescheduleRow = collect($shown['transitions'])
        ->first(fn (array $row): bool => $row['from_status'] === 'scheduled' && $row['to_status'] === 'scheduled');
    expect($rescheduleRow)->not->toBeNull()
        ->and($rescheduleRow['comment'])->toBe('تأجيل');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MEETING_RESCHEDULED,
    );
});

test('M09 cross-tenant isolation returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $meetingA = createMeetingViaApi(['title' => 'اجتماع A']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $meetingB = createMeetingViaApi(['title' => 'اجتماع B']);
    $employeeB = withTenant($tenantB, fn () => Employee::factory()->create());
    $unitB = withTenant($tenantB, fn () => OrganizationUnit::factory()->create());

    Sanctum::actingAs($ownerA);

    spaGetJson("/api/v1/meetings/{$meetingB['id']}")->assertNotFound();
    spaPatchJson("/api/v1/meetings/{$meetingB['id']}", ['title' => 'hack'])->assertNotFound();
    spaPostJson("/api/v1/meetings/{$meetingB['id']}/schedule", [
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertNotFound();
    spaDeleteJson("/api/v1/meetings/{$meetingB['id']}")->assertNotFound();

    spaGetJson('/api/v1/meetings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $meetingA['id']);

    spaPostJson('/api/v1/meetings', [
        'title' => 'موظف أجنبي',
        'chairperson_employee_id' => $employeeB->id,
    ])->assertStatus(422);

    spaPostJson('/api/v1/meetings', [
        'title' => 'وحدة أجنبية',
        'organization_unit_id' => $unitB->id,
    ])->assertStatus(422);
});

test('M10 RBAC supervisor forbidden create; auditor view only', function (): void {
    spaGetJson('/api/v1/meetings')->assertUnauthorized();

    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));

    $supervisor = tenantUser($tenant);
    assignRole($supervisor, 'supervisor');
    Sanctum::actingAs($supervisor);

    spaPostJson('/api/v1/meetings', [
        'title' => 'ممنوع',
    ])->assertForbidden();

    actingAsTenantOwner($tenant);
    $meeting = createMeetingViaApi();

    $auditor = tenantUser($tenant);
    assignRole($auditor, 'auditor');
    Sanctum::actingAs($auditor);
    $auditor->unsetRelation('roles');
    app(EffectivePermissions::class)->forgetUser($auditor);

    spaGetJson('/api/v1/meetings')->assertOk();
    spaGetJson("/api/v1/meetings/{$meeting['id']}")->assertOk();
    spaPostJson('/api/v1/meetings', [
        'title' => 'مدقق لا ينشئ',
    ])->assertForbidden();
    spaPostJson("/api/v1/meetings/{$meeting['id']}/cancel", [
        'comment' => 'ممنوع',
    ])->assertForbidden();
});

test('M11 list upcoming filter and completed not editable', function (): void {
    actingAsTenantOwner();

    $upcoming = createMeetingViaApi(['title' => 'قادم']);
    advanceMeetingTo($upcoming['id'], MeetingStatus::Scheduled->value, now()->addDays(5)->toIso8601String());

    $pastScheduled = createMeetingViaApi(['title' => 'متأخر']);
    advanceMeetingTo($pastScheduled['id'], MeetingStatus::Scheduled->value, now()->subDay()->toIso8601String());

    $draft = createMeetingViaApi(['title' => 'مسودة فقط']);

    spaGetJson('/api/v1/meetings?upcoming=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $upcoming['id']);

    spaGetJson('/api/v1/meetings?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $draft['id']);

    $completed = createMeetingViaApi(['title' => 'مكتمل']);
    advanceMeetingTo($completed['id'], MeetingStatus::Completed->value);

    spaPatchJson("/api/v1/meetings/{$completed['id']}", [
        'title' => 'ممنوع',
    ])->assertStatus(422)->assertJsonPath('code', 'MEETING_NOT_EDITABLE');

    spaPutJson("/api/v1/meetings/{$completed['id']}/minutes", [
        'minutes_body' => 'تعديل بعد الإكمال',
    ])->assertStatus(422)->assertJsonPath('code', 'MEETING_NOT_EDITABLE');
});
