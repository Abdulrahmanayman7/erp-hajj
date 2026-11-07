<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('registers risks permissions and keeps approve_assessment sensitive-explicit-only', function (): void {
    actingAsTenantOwner();

    $names = collect(PermissionCatalog::definitions())->where('module', 'risks')->pluck('name')->all();
    expect($names)->toEqualCanonicalizing([
        'risks.view',
        'risks.create',
        'risks.update',
        'risks.assess',
        'risks.approve_assessment',
        'risks.manage_treatment',
        'risks.assign',
        'risks.close',
        'risks.delete',
    ]);

    expect(PermissionCatalog::sensitiveExplicitOnlyPermissions())
        ->toContain('risks.approve_assessment');

    $owner = auth()->user();
    expect($owner->hasPermission('risks.view'))->toBeTrue()
        ->and($owner->hasPermission('risks.create'))->toBeTrue()
        ->and($owner->hasPermission('risks.approve_assessment'))->toBeFalse();
});

it('requires authentication for risk endpoints', function (): void {
    spaGetJson('/api/v1/risks')->assertUnauthorized();
    spaPostJson('/api/v1/risks', ['title' => 'x'])->assertUnauthorized();
});

it('denies risk actions without permission', function (): void {
    actingAsRiskUserWith(['dashboard.view']);
    $ctx = riskBootContext();

    spaGetJson('/api/v1/risks', $ctx['header'])->assertForbidden();
    spaPostJson('/api/v1/risks', ['title' => 'خطر'], $ctx['header'])->assertForbidden();
});

it('creates lists shows updates and soft-deletes an identified risk', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $created = spaPostJson('/api/v1/risks', [
        'title' => 'خطر تشغيل',
        'description' => 'وصف',
    ], $ctx['header'])->assertCreated()->json('data');

    expect($created['status'])->toBe('identified')
        ->and($created['risk_number'])->toMatch('/^RSK-\d{4}-\d{6}$/')
        ->and($created['season_id'])->toBe($ctx['season']['id']);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RISK_CREATED);

    spaGetJson('/api/v1/risks', $ctx['header'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    spaGetJson('/api/v1/risks/'.$created['id'], $ctx['header'])
        ->assertOk()
        ->assertJsonPath('data.title', 'خطر تشغيل');

    spaPatchJson('/api/v1/risks/'.$created['id'], [
        'title' => 'خطر محدّث',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.title', 'خطر محدّث');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RISK_UPDATED);

    spaDeleteJson('/api/v1/risks/'.$created['id'], $ctx['header'])->assertOk();
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RISK_DELETED);

    spaGetJson('/api/v1/risks/'.$created['id'], $ctx['header'])->assertNotFound();
});

it('enforces assessment score severity boundaries', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $cases = [
        [1, 1, 1, 'low'],
        [2, 2, 4, 'low'],
        [3, 2, 6, 'medium'],
        [5, 2, 10, 'high'],
        [5, 4, 20, 'critical'],
        [5, 5, 25, 'critical'],
    ];

    foreach ($cases as [$l, $i, $score, $severity]) {
        $risk = spaPostJson('/api/v1/risks', ['title' => "R {$l}x{$i}"], $ctx['header'])->assertCreated()->json('data');
        $assessed = spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', [
            'likelihood' => $l,
            'impact' => $i,
        ], $ctx['header'])->assertOk()->json('data');

        expect($assessed['score'])->toBe($score)
            ->and($assessed['severity_level'])->toBe($severity)
            ->and($assessed['status'])->toBe('under_assessment');
    }

    $bad = spaPostJson('/api/v1/risks', ['title' => 'bad'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$bad['id'].'/assess', [
        'likelihood' => 0,
        'impact' => 3,
    ], $ctx['header'])->assertStatus(422);
});

it('runs full lifecycle with treatment assignment and closure', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);

    $creator = actingAsTenantOwner();
    $tenant = $creator->tenant;
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'دورة كاملة'], $ctx['header'])
        ->assertCreated()->json('data');

    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', [
        'likelihood' => 4,
        'impact' => 4,
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'under_assessment');

    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'تنفيذ إجراءات',
        'owner_employee_id' => $ctx['employee']->id,
        'status' => 'planned',
    ], $ctx['header'])->assertOk();

    $approver = actingAsRiskUserWith(allRiskPermissions(), $tenant);
    Sanctum::actingAs($approver);

    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RISK_ASSESSMENT_APPROVED);

    spaPostJson('/api/v1/risks/'.$risk['id'].'/assign', [
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'treating');

    spaPostJson('/api/v1/risks/'.$risk['id'].'/close', [
        'closure_outcome' => 'mitigated',
        'close_reason' => 'تم',
    ], $ctx['header'])->assertOk()->assertJsonPath('data.status', 'closed');

    spaPatchJson('/api/v1/risks/'.$risk['id'], ['title' => 'x'], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_IMMUTABLE');
    spaDeleteJson('/api/v1/risks/'.$risk['id'], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_DELETE_NOT_ALLOWED');
});

it('forbids self-approval even with approve_assessment permission', function (): void {
    $creator = actingAsRiskUserWith(allRiskPermissions());
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'SoD'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 3, 'impact' => 3], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk();

    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_SELF_APPROVAL_FORBIDDEN');

    expect($creator->id)->toBe(auth()->id());
});

it('requires treatment and assessment before approval', function (): void {
    $creator = actingAsTenantOwner();
    $tenant = $creator->tenant;
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'نقص'], $ctx['header'])->assertCreated()->json('data');

    $approver = actingAsRiskUserWith(allRiskPermissions(), $tenant);

    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT_STALE_STATE');

    Sanctum::actingAs($creator);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();

    Sanctum::actingAs($approver);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_TREATMENT_REQUIRED');
});

it('returns conflict on stale state transitions', function (): void {
    $creator = actingAsTenantOwner();
    $tenant = $creator->tenant;
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'stale'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 3], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk();

    $approver = actingAsRiskUserWith(allRiskPermissions(), $tenant);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();

    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT_STALE_STATE');

    spaPostJson('/api/v1/risks/'.$risk['id'].'/close', [
        'closure_outcome' => 'accepted',
    ], $ctx['header'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT_STALE_STATE');
});

it('isolates risks across tenants and seasons', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    $ctxA = riskBootContext();
    $risk = spaPostJson('/api/v1/risks', ['title' => 'A'], $ctxA['header'])->assertCreated()->json('data');

    $seasonB = withTenant($tenantA, fn () => Season::factory()->create());
    spaGetJson('/api/v1/risks/'.$risk['id'], seasonHeader($seasonB->id))->assertNotFound();

    $ownerB = actingAsTenantOwner();
    $ctxB = riskBootContext();
    spaGetJson('/api/v1/risks/'.$risk['id'], $ctxB['header'])->assertNotFound();
    spaGetJson('/api/v1/risks', $ctxB['header'])->assertOk()->assertJsonPath('meta.total', 0);

    Sanctum::actingAs($ownerA);
    expect($tenantA->id)->not->toBe($ownerB->tenant_id);
});

it('rejects project_id when SeasonProjects is unavailable', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    spaPostJson('/api/v1/risks', [
        'title' => 'مشروع',
        'project_id' => 1,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_LINK_UNAVAILABLE');
});

it('rejects invalid linked entities', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    spaPostJson('/api/v1/risks', [
        'title' => 'رابط خاطئ',
        'group_id' => 999999,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'LINKED_ENTITY_INVALID');
});

it('blocks soft-delete after assessment progression', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'لا حذف'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 1, 'impact' => 1], $ctx['header'])->assertOk();

    spaDeleteJson('/api/v1/risks/'.$risk['id'], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_DELETE_NOT_ALLOWED');
});

it('writes audit records for material risk changes', function (): void {
    $creator = actingAsTenantOwner();
    $tenant = $creator->tenant;
    $ctx = riskBootContext();

    $risk = spaPostJson('/api/v1/risks', ['title' => 'تدقيق'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 3, 'impact' => 3], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk();

    $approver = actingAsRiskUserWith(allRiskPermissions(), $tenant);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assign', [
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/close', [
        'closure_outcome' => 'accepted',
    ], $ctx['header'])->assertOk();

    $events = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->whereIn('event_type', [
            'RISK_CREATED',
            'RISK_ASSESSED',
            'RISK_TREATMENT_UPSERTED',
            'RISK_ASSESSMENT_APPROVED',
            'RISK_ASSIGNED',
            'RISK_CLOSED',
        ])
        ->pluck('event_type')
        ->all();

    expect($events)->toEqualCanonicalizing([
        'RISK_CREATED',
        'RISK_ASSESSED',
        'RISK_TREATMENT_UPSERTED',
        'RISK_ASSESSMENT_APPROVED',
        'RISK_ASSIGNED',
        'RISK_CLOSED',
    ]);
});

it('allocates unique risk numbers per season year', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();

    $a = spaPostJson('/api/v1/risks', ['title' => '1'], $ctx['header'])->assertCreated()->json('data');
    $b = spaPostJson('/api/v1/risks', ['title' => '2'], $ctx['header'])->assertCreated()->json('data');

    expect($a['risk_number'])->not->toBe($b['risk_number']);

    spaDeleteJson('/api/v1/risks/'.$a['id'], $ctx['header'])->assertOk();
    $c = spaPostJson('/api/v1/risks', ['title' => '3'], $ctx['header'])->assertCreated()->json('data');
    expect($c['risk_number'])->not->toBe($a['risk_number']);
});

it('checks each risks permission gate independently', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $risk = withTenant($tenant, function () use ($owner, $ctx): OperationalRisk {
        return OperationalRisk::factory()->forSeason(
            Season::query()->findOrFail($ctx['season']['id'])
        )->create([
            'created_by' => $owner->id,
            'status' => RiskStatus::Identified,
            'title' => 'perm',
        ]);
    });

    $matrix = [
        'risks.view' => fn () => spaGetJson('/api/v1/risks/'.$risk->id, $ctx['header']),
        'risks.create' => fn () => spaPostJson('/api/v1/risks', ['title' => 'x'], $ctx['header']),
        'risks.update' => fn () => spaPatchJson('/api/v1/risks/'.$risk->id, ['title' => 'y'], $ctx['header']),
        'risks.assess' => fn () => spaPostJson('/api/v1/risks/'.$risk->id.'/assess', ['likelihood' => 1, 'impact' => 1], $ctx['header']),
        'risks.delete' => fn () => spaDeleteJson('/api/v1/risks/'.$risk->id, $ctx['header']),
    ];

    foreach ($matrix as $needed => $call) {
        actingAsRiskUserWith(riskPermissionsExcept([$needed]), $tenant);
        $call()->assertForbidden();
    }
});

it('rejects treatment while identified', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();
    $risk = spaPostJson('/api/v1/risks', ['title' => 'علاج مبكر'], $ctx['header'])->assertCreated()->json('data');

    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $ctx['employee']->id,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'RISK_INVALID_TRANSITION');
});

it('validates treatment employee belongs to tenant', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();
    $risk = spaPostJson('/api/v1/risks', ['title' => 'موظف'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();

    $foreignTenant = Tenant::factory()->create();
    $foreignEmployee = withTenant($foreignTenant, function () use ($foreignTenant): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $foreignTenant->id]);

        return Employee::factory()->create(['organization_unit_id' => $unit->id, 'status' => 'active']);
    });

    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $foreignEmployee->id,
    ], $ctx['header'])->assertStatus(422);
});
