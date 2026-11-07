<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalNotificationOccurrence;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

it('maps risk and complaint audit entities to operational_* types', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'كيان تدقيق'], $ctx['header'])
        ->assertCreated()
        ->json('data');

    $log = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', 'RISK_CREATED')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->entity_type)->toBe('operational_risk')
        ->and((int) $log?->entity_id)->toBe((int) $risk['id'])
        ->and($log?->entity_number)->toBe($risk['risk_number']);
});

it('returns risk timeline for viewers without requiring audit.view', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'خط زمني'], $ctx['header'])
        ->assertCreated()
        ->json('data');

    $viewer = actingAsRiskUserWith(['risks.view'], $tenant);
    Sanctum::actingAs($viewer);

    $timeline = spaGetJson('/api/v1/risks/'.$risk['id'].'/timeline', $ctx['header'])
        ->assertOk()
        ->json('data');

    expect($timeline)->toBeArray()->not->toBeEmpty();
    expect(collect($timeline)->pluck('event_type')->all())->toContain('RISK_CREATED');
});

it('denies timeline without risks.view', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'ممنوع'], $ctx['header'])
        ->assertCreated()
        ->json('data');

    $outsider = actingAsRiskUserWith(['complaints.view'], $tenant);
    Sanctum::actingAs($outsider);
    spaGetJson('/api/v1/risks/'.$risk['id'].'/timeline', $ctx['header'])->assertForbidden();
});

it('reports season projects available when S06 is present', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $caps = spaGetJson('/api/v1/operational-links/capabilities', $ctx['header'])
        ->assertOk()
        ->json('data');

    expect($caps['season_projects_available'])->toBeTrue()
        ->and($caps['season_projects_reason'])->toBeNull();
});

it('rejects project_id when SeasonProjects table is unavailable', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    withTenant($tenant, function () use ($tenant): void {
        $season = Season::factory()->active()->create([
            'tenant_id' => $tenant->id,
        ]);

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('season_projects')
            ->andReturn(false);

        expect(fn () => app(RiskReferenceValidator::class)
            ->resolveProject($season, 1))
            ->toThrow(RisksAndComplaintsDomainException::class, 'ربط المشروع الموسمي غير متاح حالياً.');
    });
});

it('allows escalate via assignee path and escalate permission path', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    // Path A: escalate permission without being assignee.
    $escalator = actingAsRiskUserWith(['complaints.view', 'complaints.escalate'], $tenant);
    $assigneeUser = actingAsRiskUserWith(
        complaintPermissionsExcept(['complaints.close', 'complaints.reopen', 'complaints.escalate']),
        $tenant,
    );
    $linked = withTenant($tenant, function () use ($tenant, $assigneeUser) {
        $unit = OrganizationUnit::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });
    $target = createRiskEmployee($tenant);
    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'تصعيد مزدوج',
        'body' => 'نص',
        'category_code' => 'service',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();

    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($escalator);
    expect($escalator->hasPermission('complaints.escalate'))->toBeTrue();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $target->id,
        'escalate_reason' => 'تصعيد بصلاحية',
    ], $ctx['header'])->assertOk();

    // Path B: assignee without escalate permission (fresh complaint).
    Sanctum::actingAs($owner);
    $complaint2 = spaPostJson('/api/v1/complaints', [
        'title' => 'تصعيد المكلّف',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint2['id'], ['start_review' => true], $ctx['header'])->assertOk();
    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint2['id'].'/assign', [
        'assignee_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    expect($assigneeUser->hasPermission('complaints.escalate'))->toBeFalse();
    $target2 = createRiskEmployee($tenant);
    spaPostJson('/api/v1/complaints/'.$complaint2['id'].'/escalate', [
        'escalated_to_employee_id' => $target2->id,
        'escalate_reason' => 'تصعيد من المكلّف',
    ], $ctx['header'])->assertOk();
});

it('UAT: full risk lifecycle with timeline and notifications', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $approver = actingAsRiskUserWith(['risks.view', 'risks.approve_assessment', 'risks.assign', 'risks.close'], $tenant);
    $assignee = actingAsRiskUserWith(['risks.view'], $tenant);
    $linked = withTenant($tenant, function () use ($tenant, $assignee) {
        $unit = OrganizationUnit::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assignee->id,
        ]);
    });

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'UAT خطر'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 4, 'impact' => 4], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة UAT',
        'plan_body' => 'نص',
        'owner_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();

    // Creator self-approval rejected.
    Sanctum::actingAs($owner);
    grantRiskPermissions($owner, ['risks.approve_assessment']);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertStatus(422);

    Sanctum::actingAs($approver);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assign', [
        'owner_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/close', [
        'closure_outcome' => 'mitigated',
    ], $ctx['header'])->assertOk();

    $timeline = spaGetJson('/api/v1/risks/'.$risk['id'].'/timeline', $ctx['header'])->assertOk()->json('data');
    expect(collect($timeline)->pluck('event_type'))->toContain(
        'RISK_CREATED',
        'RISK_ASSESSED',
        'RISK_ASSESSMENT_APPROVED',
        'RISK_ASSIGNED',
        'RISK_CLOSED',
    );

    withTenant($tenant, function () use ($assignee): void {
        expect(Notification::query()
            ->where('recipient_user_id', $assignee->id)
            ->where('type', NotificationType::RiskAssigned)
            ->count())->toBe(1);
    });
});

it('UAT: complaint reopen with reason and distinct resolve notifications', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $linked = withTenant($tenant, function () use ($tenant, $assigneeUser) {
        $unit = OrganizationUnit::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assigneeUser->id,
        ]);
    });
    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'UAT شكوى',
        'body' => 'نص سري',
        'category_code' => 'service',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();
    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل 1',
    ], $ctx['header'])->assertOk();
    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'إغلاق',
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => '',
    ], $ctx['header'])->assertStatus(422);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'سبب إلزامي',
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();
    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل 2',
    ], $ctx['header'])->assertOk();

    withTenant($tenant, function () use ($closer): void {
        expect(Notification::query()
            ->where('type', NotificationType::ComplaintResolved)
            ->where('recipient_user_id', $closer->id)
            ->count())->toBe(2);
        expect(OperationalNotificationOccurrence::query()->where('event_kind', 'resolve')->count())->toBe(2);
    });
});

it('UAT: cross-tenant risk show returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $ctxA = riskBootContext();
    Sanctum::actingAs($ownerA);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'عزل'], $ctxA['header'])->assertCreated()->json('data');

    $ownerB = actingAsTenantOwner();
    $ctxB = riskBootContext();
    Sanctum::actingAs($ownerB);
    spaGetJson('/api/v1/risks/'.$risk['id'], $ctxB['header'])->assertNotFound();
    spaGetJson('/api/v1/risks/'.$risk['id'].'/timeline', $ctxB['header'])->assertNotFound();
});

it('UAT: rolled-back assign produces zero notification and occurrence', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();
    $assignee = actingAsRiskUserWith(['risks.view'], $tenant);
    $linked = withTenant($tenant, function () use ($tenant, $assignee) {
        $unit = OrganizationUnit::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $assignee->id,
        ]);
    });

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'rollback uat'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $linked->id,
    ], $ctx['header'])->assertOk();
    actingAsRiskUserWith(allRiskPermissions(), $tenant);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();

    $beforeNotes = withTenant($tenant, fn () => Notification::query()->count());
    $beforeOcc = withTenant($tenant, fn () => OperationalNotificationOccurrence::query()->count());

    try {
        withTenant($tenant, function () use ($risk, $linked): void {
            DB::transaction(function () use ($risk, $linked): void {
                $model = OperationalRisk::query()
                    ->lockForUpdate()
                    ->findOrFail($risk['id']);
                $model->forceFill([
                    'status' => RiskStatus::Treating->value,
                    'owner_employee_id' => $linked->id,
                    'assigned_at' => now(),
                    'assigned_by' => auth()->id(),
                ])->save();
                $occ = app(OperationalNotificationOccurrenceRecorder::class)
                    ->record('operational_risk', (int) $model->id, 'assign');
                app(NotificationHooks::class)
                    ->riskAssigned($model->fresh(['ownerEmployee']), $occ);
                throw new RuntimeException('force rollback');
            });
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(withTenant($tenant, fn () => Notification::query()->count()))->toBe($beforeNotes)
        ->and(withTenant($tenant, fn () => OperationalNotificationOccurrence::query()->count()))->toBe($beforeOcc);
});
