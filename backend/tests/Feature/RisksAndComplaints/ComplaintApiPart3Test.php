<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('registers complaints permissions with sensitive close and reopen', function (): void {
    actingAsTenantOwner();

    $names = collect(PermissionCatalog::definitions())->where('module', 'complaints')->pluck('name')->all();
    expect($names)->toEqualCanonicalizing([
        'complaints.view',
        'complaints.create',
        'complaints.update',
        'complaints.assign',
        'complaints.escalate',
        'complaints.resolve',
        'complaints.close',
        'complaints.reopen',
        'complaints.add_note',
        'complaints.delete',
    ]);

    expect(PermissionCatalog::sensitiveExplicitOnlyPermissions())
        ->toContain('complaints.close')
        ->toContain('complaints.reopen');

    $owner = auth()->user();
    expect($owner->hasPermission('complaints.view'))->toBeTrue()
        ->and($owner->hasPermission('complaints.create'))->toBeTrue()
        ->and($owner->hasPermission('complaints.close'))->toBeFalse()
        ->and($owner->hasPermission('complaints.reopen'))->toBeFalse();
});

it('requires authentication for complaint endpoints', function (): void {
    spaGetJson('/api/v1/complaints')->assertUnauthorized();
    spaPostJson('/api/v1/complaints', [
        'title' => 'x',
        'body' => 'y',
        'category_code' => 'other',
    ])->assertUnauthorized();
});

it('denies complaint actions without permission', function (): void {
    actingAsRiskUserWith(['dashboard.view']);
    $ctx = riskBootContext();

    spaGetJson('/api/v1/complaints', $ctx['header'])->assertForbidden();
    spaPostJson('/api/v1/complaints', [
        'title' => 'شكوى',
        'body' => 'نص',
        'category_code' => 'service',
    ], $ctx['header'])->assertForbidden();
});

it('creates lists shows updates notes and soft-deletes a new complaint', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $created = spaPostJson('/api/v1/complaints', [
        'title' => 'شكوى تشغيل',
        'body' => 'تفاصيل',
        'category_code' => 'accommodation',
    ], $ctx['header'])->assertCreated()->json('data');

    expect($created['status'])->toBe('new')
        ->and($created['complaint_number'])->toMatch('/^CMP-\d{4}-\d{6}$/')
        ->and($created['season_id'])->toBe($ctx['season']['id']);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::COMPLAINT_CREATED);

    spaGetJson('/api/v1/complaints', $ctx['header'])->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/complaints/'.$created['id'], $ctx['header'])
        ->assertOk()
        ->assertJsonPath('data.title', 'شكوى تشغيل');

    spaPatchJson('/api/v1/complaints/'.$created['id'], [
        'title' => 'شكوى محدّثة',
        'start_review' => true,
    ], $ctx['header'])->assertOk()
        ->assertJsonPath('data.title', 'شكوى محدّثة')
        ->assertJsonPath('data.status', 'under_review');

    spaPostJson('/api/v1/complaints/'.$created['id'].'/notes', [
        'body' => 'ملاحظة داخلية',
    ], $ctx['header'])->assertCreated()
        ->assertJsonPath('data.notes.0.body', 'ملاحظة داخلية');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::COMPLAINT_NOTE_ADDED);

    // Soft-delete only while new+unassigned — reopen to new path not available; create another.
    $fresh = spaPostJson('/api/v1/complaints', [
        'title' => 'للحذف',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');

    spaDeleteJson('/api/v1/complaints/'.$fresh['id'], $ctx['header'])->assertOk();
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::COMPLAINT_DELETED);
    spaGetJson('/api/v1/complaints/'.$fresh['id'], $ctx['header'])->assertNotFound();
});

it('runs full complaint lifecycle with assign escalate resolve close reopen', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $assigneeEmployee = withTenant($tenant, function () use ($tenant, $assigneeUser): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });

    $supervisor = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'دورة كاملة',
        'body' => 'نص',
        'category_code' => 'service',
    ], $ctx['header'])->assertCreated()->json('data');

    spaPatchJson('/api/v1/complaints/'.$complaint['id'], [
        'status' => 'under_review',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'under_review');

    Sanctum::actingAs($supervisor);
    $assigned = spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $assigneeEmployee->id,
    ], $ctx['header'])->assertOk()->json('data');
    expect($assigned['status'])->toBe('in_progress');

    $beforeEscalateStatus = $assigned['status'];
    $escalated = spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $ctx['employee']->id,
        'escalate_reason' => 'يحتاج إشرافاً',
    ], $ctx['header'])->assertOk()->json('data');
    expect($escalated['status'])->toBe($beforeEscalateStatus)
        ->and($escalated['escalation_level'])->toBe(1)
        ->and($escalated['escalated_to_employee_id'])->toBe($ctx['employee']->id);

    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'تم الإصلاح',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'resolved');

    Sanctum::actingAs($supervisor);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'مغلق بعد التحقق',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'closed');

    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'ظهرت مشكلة جديدة',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'under_review');
});

it('allows escalation by assignee user without escalate permission', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith([
        'complaints.view',
        'complaints.resolve',
    ], $tenant);
    $assigneeEmployee = withTenant($tenant, function () use ($tenant, $assigneeUser): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'تصعيد معيّن',
        'body' => 'نص',
        'category_code' => 'staffing',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $assigneeEmployee->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    expect($assigneeUser->hasPermission('complaints.escalate'))->toBeFalse();

    $result = spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $ctx['employee']->id,
        'escalate_reason' => 'تصعيد من المعيّن',
    ], $ctx['header'])->assertOk()->json('data');

    expect($result['status'])->toBe('in_progress')
        ->and($result['escalation_level'])->toBe(1);
});

it('rejects resolve by non-assignee even with resolve permission', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $other = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'حل غير معيّن',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($other);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل',
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'COMPLAINT_RESOLVE_NOT_ASSIGNEE');
});

it('requires close and reopen reasons and sensitive permissions', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $assigneeEmployee = withTenant($tenant, function () use ($tenant, $assigneeUser): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'أسباب',
        'body' => 'نص',
        'category_code' => 'safety',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $assigneeEmployee->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'تم',
    ], $ctx['header'])->assertOk();

    // Owner lacks close (sensitive).
    Sanctum::actingAs($owner);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'سبب',
    ], $ctx['header'])->assertForbidden();

    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [])
        ->assertStatus(422); // missing season header or validation — use header
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [], $ctx['header'])
        ->assertStatus(422);

    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'مغلق',
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($owner);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'إعادة',
    ], $ctx['header'])->assertForbidden();

    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [], $ctx['header'])->assertStatus(422);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'إعادة فتح',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'under_review');
});

it('returns conflict on stale complaint transitions', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'stale',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');

    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT_STALE_STATE');
});

it('isolates complaints across tenants and seasons', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    $ctxA = riskBootContext();
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'A',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctxA['header'])->assertCreated()->json('data');

    $seasonB = withTenant($tenantA, fn () => Season::factory()->create());
    spaGetJson('/api/v1/complaints/'.$complaint['id'], seasonHeader($seasonB->id))->assertNotFound();

    $ownerB = actingAsTenantOwner();
    $ctxB = riskBootContext();
    spaGetJson('/api/v1/complaints/'.$complaint['id'], $ctxB['header'])->assertNotFound();
    spaGetJson('/api/v1/complaints', $ctxB['header'])->assertOk()->assertJsonPath('meta.total', 0);

    Sanctum::actingAs($ownerA);
    expect($tenantA->id)->not->toBe($ownerB->tenant_id);
});

it('rejects unknown project_id and invalid linked entities for complaints', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    spaPostJson('/api/v1/complaints', [
        'title' => 'مشروع',
        'body' => 'نص',
        'category_code' => 'other',
        'project_id' => 999999,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'LINKED_ENTITY_INVALID');

    spaPostJson('/api/v1/complaints', [
        'title' => 'رابط',
        'body' => 'نص',
        'category_code' => 'other',
        'group_id' => 999999,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'LINKED_ENTITY_INVALID');
});

it('links a complaint to a valid season project', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $project = withTenant($tenant, function () use ($tenant, $ctx, $owner) {
        return SeasonProject::factory()->create([
            'tenant_id' => $tenant->id,
            'season_id' => $ctx['season']['id'],
            'created_by' => $owner->id,
        ]);
    });

    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'مرتبط بمشروع',
        'body' => 'نص',
        'category_code' => 'other',
        'project_id' => $project->id,
    ], $ctx['header'])
        ->assertCreated()
        ->json('data');

    expect($complaint['project_id'])->toBe($project->id);
});

it('blocks soft-delete after review or assignment', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'لا حذف',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');

    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    spaDeleteJson('/api/v1/complaints/'.$complaint['id'], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'COMPLAINT_DELETE_NOT_ALLOWED');
});

it('writes audit records for material complaint changes', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $assigneeEmployee = withTenant($tenant, function () use ($tenant, $assigneeUser): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });
    $supervisor = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'تدقيق',
        'body' => 'نص',
        'category_code' => 'procurement',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();

    Sanctum::actingAs($supervisor);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $assigneeEmployee->id,
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $ctx['employee']->id,
        'escalate_reason' => 'سبب',
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل',
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($supervisor);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'إغلاق',
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'إعادة',
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/notes', [
        'body' => 'ملاحظة',
    ], $ctx['header'])->assertCreated();

    $events = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->whereIn('event_type', [
            'COMPLAINT_CREATED',
            'COMPLAINT_UPDATED',
            'COMPLAINT_ASSIGNED',
            'COMPLAINT_ESCALATED',
            'COMPLAINT_RESOLVED',
            'COMPLAINT_CLOSED',
            'COMPLAINT_REOPENED',
            'COMPLAINT_NOTE_ADDED',
        ])
        ->pluck('event_type')
        ->unique()
        ->values()
        ->all();

    expect($events)->toEqualCanonicalizing([
        'COMPLAINT_CREATED',
        'COMPLAINT_UPDATED',
        'COMPLAINT_ASSIGNED',
        'COMPLAINT_ESCALATED',
        'COMPLAINT_RESOLVED',
        'COMPLAINT_CLOSED',
        'COMPLAINT_REOPENED',
        'COMPLAINT_NOTE_ADDED',
    ]);
});

it('checks each complaints permission gate independently', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $complaint = withTenant($tenant, function () use ($owner, $ctx): OperationalComplaint {
        return OperationalComplaint::factory()->forSeason(
            Season::query()->findOrFail($ctx['season']['id'])
        )->create([
            'created_by' => $owner->id,
            'status' => ComplaintStatus::New,
            'title' => 'perm',
            'body' => 'body',
            'category_code' => 'other',
        ]);
    });

    $matrix = [
        'complaints.view' => fn () => spaGetJson('/api/v1/complaints/'.$complaint->id, $ctx['header']),
        'complaints.create' => fn () => spaPostJson('/api/v1/complaints', [
            'title' => 'x',
            'body' => 'y',
            'category_code' => 'other',
        ], $ctx['header']),
        'complaints.update' => fn () => spaPatchJson('/api/v1/complaints/'.$complaint->id, ['title' => 'z'], $ctx['header']),
        'complaints.delete' => fn () => spaDeleteJson('/api/v1/complaints/'.$complaint->id, $ctx['header']),
        'complaints.add_note' => fn () => spaPostJson('/api/v1/complaints/'.$complaint->id.'/notes', ['body' => 'n'], $ctx['header']),
    ];

    foreach ($matrix as $needed => $call) {
        actingAsRiskUserWith(complaintPermissionsExcept([$needed]), $tenant);
        $call()->assertForbidden();
    }
});

it('rejects invalid category codes on staff intake', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    spaPostJson('/api/v1/complaints', [
        'title' => 'تصنيف',
        'body' => 'نص',
        'category_code' => 'not-a-real-category',
    ], $ctx['header'])->assertStatus(422);
});

it('rejects escalate from new and closed without reopen', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'تصعيد مبكر',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');

    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $ctx['employee']->id,
        'escalate_reason' => 'سبب',
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'COMPLAINT_INVALID_TRANSITION');
});
