<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalNotificationOccurrence;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{user: User, employee: Employee}
 */
function linkEmployeeUser(Tenant $tenant, User $user): array
{
    $employee = withTenant($tenant, function () use ($tenant, $user): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
            'user_id' => $user->id,
        ]);
    });

    return ['user' => $user, 'employee' => $employee];
}

/**
 * @param  callable(): mixed  $callback
 */
function withNotifications(Tenant $tenant, callable $callback): mixed
{
    return withTenant($tenant, $callback);
}

it('creates durable occurrence table for S07 notification dedupe', function (): void {
    expect(Schema::hasTable('operational_notification_occurrences'))->toBeTrue()
        ->and(Schema::hasColumn('operational_notification_occurrences', 'tenant_id'))->toBeTrue()
        ->and(Schema::hasColumn('operational_notification_occurrences', 'event_kind'))->toBeTrue();
});

it('registers S07 notification types in the curated catalog', function (): void {
    $values = array_map(static fn (NotificationType $t): string => $t->value, NotificationType::cases());

    expect($values)->toContain(
        'RISK_ASSIGNED',
        'COMPLAINT_ASSIGNED',
        'COMPLAINT_ESCALATED',
        'RISK_SEVERITY_ALERT',
        'COMPLAINT_RESOLVED',
        'COMPLAINT_REOPENED',
    );
});

it('notifies assignee on risk assignment and skips missing user_id', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assignee = actingAsRiskUserWith(['risks.view', 'risks.assign'], $tenant);
    $linked = linkEmployeeUser($tenant, $assignee);

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'تعيين'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();

    actingAsRiskUserWith(allRiskPermissions(), $tenant);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assign', [
        'owner_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($assignee): void {
        expect(Notification::query()
            ->where('recipient_user_id', $assignee->id)
            ->where('type', NotificationType::RiskAssigned)
            ->count())->toBe(1);
        expect(OperationalNotificationOccurrence::query()->where('event_kind', 'assign')->count())->toBeGreaterThan(0);
    });

    Log::spy();
    $orphan = createRiskEmployee($tenant);
    $beforeCount = withNotifications($tenant, fn () => Notification::query()->count());
    withTenant($tenant, function () use ($owner, $ctx, $orphan): void {
        $season = Season::query()->findOrFail($ctx['season']['id']);
        $risk2 = OperationalRisk::factory()->forSeason($season)->create([
            'created_by' => $owner->id,
            'status' => RiskStatus::Approved,
            'likelihood' => 2,
            'impact' => 2,
            'title' => 'orphan',
            'owner_employee_id' => $orphan->id,
        ]);
        $occ = app(OperationalNotificationOccurrenceRecorder::class)->record(
            OperationalNotificationOccurrenceRecorder::ENTITY_RISK,
            (int) $risk2->id,
            OperationalNotificationOccurrenceRecorder::KIND_ASSIGN,
        );
        app(NotificationHooks::class)->riskAssigned($risk2, $occ);
    });
    expect(withNotifications($tenant, fn () => Notification::query()->count()))->toBe($beforeCount);
    Log::shouldHaveReceived('info')->withArgs(fn ($msg): bool => $msg === 'Notification recipient skipped.');
});

it('assigns A then B then A again yielding two distinct notifications to A', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $userA = actingAsRiskUserWith(['risks.view'], $tenant);
    $userB = actingAsRiskUserWith(['risks.view'], $tenant);
    $empA = linkEmployeeUser($tenant, $userA)['employee'];
    $empB = linkEmployeeUser($tenant, $userB)['employee'];

    withTenant($tenant, function () use ($owner, $empA, $empB, $userA): void {
        $season = Season::factory()->active()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create([
            'created_by' => $owner->id,
            'status' => RiskStatus::Treating,
            'title' => 'reassign',
            'owner_employee_id' => $empA->id,
        ]);

        $recorder = app(OperationalNotificationOccurrenceRecorder::class);
        $hooks = app(NotificationHooks::class);

        $risk->owner_employee_id = $empA->id;
        $occ1 = $recorder->record('operational_risk', (int) $risk->id, 'assign');
        $hooks->riskAssigned($risk, $occ1);

        $risk->owner_employee_id = $empB->id;
        $occ2 = $recorder->record('operational_risk', (int) $risk->id, 'assign');
        $hooks->riskAssigned($risk, $occ2);

        $risk->owner_employee_id = $empA->id;
        $occ3 = $recorder->record('operational_risk', (int) $risk->id, 'assign');
        $hooks->riskAssigned($risk, $occ3);

        // Retry identical occurrence → still one for that id.
        $hooks->riskAssigned($risk, $occ3);

        expect(Notification::query()
            ->where('type', NotificationType::RiskAssigned)
            ->where('recipient_user_id', $userA->id)
            ->count())->toBe(2)
            ->and($occ1)->not->toBe($occ3);
    });
});

it('notifies on complaint assign escalate resolve reopen with correct recipients', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $linked = linkEmployeeUser($tenant, $assigneeUser);
    $targetUser = actingAsRiskUserWith(['complaints.view'], $tenant);
    $target = linkEmployeeUser($tenant, $targetUser);
    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'إشعارات',
        'body' => 'نص سري لا يظهر في الإشعار',
        'category_code' => 'service',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();

    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($assigneeUser): void {
        $assignedNote = Notification::query()
            ->where('type', NotificationType::ComplaintAssigned)
            ->where('recipient_user_id', $assigneeUser->id)
            ->first();
        expect($assignedNote)->not->toBeNull()
            ->and($assignedNote?->body)->not->toContain('نص سري');
    });

    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/escalate', [
        'escalated_to_employee_id' => $target['employee']->id,
        'escalate_reason' => 'سبب',
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل',
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/close', [
        'close_reason' => 'إغلاق',
    ], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/reopen', [
        'reopen_reason' => 'إعادة',
    ], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($assigneeUser, $targetUser, $closer): void {
        expect(Notification::query()->where('type', NotificationType::ComplaintEscalated)->where('recipient_user_id', $targetUser->id)->count())->toBe(1)
            ->and(Notification::query()->where('type', NotificationType::ComplaintResolved)->where('recipient_user_id', $closer->id)->count())->toBe(1)
            ->and(Notification::query()->where('type', NotificationType::ComplaintReopened)->where('recipient_user_id', $assigneeUser->id)->count())->toBe(1);
    });
});

it('emits distinct resolution notifications across resolve-close-reopen-resolve', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $linked = linkEmployeeUser($tenant, $assigneeUser);
    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'حل مزدوج',
        'body' => 'نص',
        'category_code' => 'other',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();

    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked['employee']->id,
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
        'reopen_reason' => 'إعادة فتح لحل جديد',
    ], $ctx['header'])->assertOk();
    // Reopen returns under_review — re-assign to reach in_progress before second resolve.
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();

    Sanctum::actingAs($assigneeUser);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/resolve', [
        'resolution_text' => 'حل 2',
    ], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($closer): void {
        expect(Notification::query()
            ->where('type', NotificationType::ComplaintResolved)
            ->where('recipient_user_id', $closer->id)
            ->count())->toBe(2);
    });
});

it('emits distinct severity alerts when reassessment re-enters the high/critical band', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $alertUser = actingAsRiskUserWith(['risks.view', 'risks.approve_assessment'], $tenant);

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'خطورة'], $ctx['header'])->assertCreated()->json('data');

    // Medium — no alert band.
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();
    withNotifications($tenant, function () use ($alertUser): void {
        expect(Notification::query()
            ->where('type', NotificationType::RiskSeverityAlert)
            ->where('recipient_user_id', $alertUser->id)
            ->count())->toBe(0);
    });

    // Enter critical — first assess occurrence.
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 5, 'impact' => 5], $ctx['header'])->assertOk();
    withNotifications($tenant, function () use ($alertUser): void {
        expect(Notification::query()
            ->where('type', NotificationType::RiskSeverityAlert)
            ->where('recipient_user_id', $alertUser->id)
            ->count())->toBe(1);
    });

    // Leave band then re-enter same critical score — second occurrence.
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 5, 'impact' => 5], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($alertUser): void {
        expect(Notification::query()
            ->where('type', NotificationType::RiskSeverityAlert)
            ->where('recipient_user_id', $alertUser->id)
            ->count())->toBe(2);
    });
});

it('emits severity alert on approval with a distinct approve occurrence', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $alertUser = actingAsRiskUserWith(['risks.view', 'risks.approve_assessment'], $tenant);

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'اعتماد خطورة'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 5, 'impact' => 5], $ctx['header'])->assertOk();
    $employee = createRiskEmployee($tenant);
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $employee->id,
    ], $ctx['header'])->assertOk();

    $beforeApprove = withNotifications($tenant, fn () => Notification::query()
        ->where('type', NotificationType::RiskSeverityAlert)
        ->where('recipient_user_id', $alertUser->id)
        ->count());

    Sanctum::actingAs($alertUser);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($alertUser, $beforeApprove): void {
        expect(Notification::query()
            ->where('type', NotificationType::RiskSeverityAlert)
            ->where('recipient_user_id', $alertUser->id)
            ->count())->toBe($beforeApprove + 1);
    });
});

it('retries identical occurrence without duplicate notification', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $user = actingAsRiskUserWith(['risks.view'], $tenant);
    $emp = linkEmployeeUser($tenant, $user)['employee'];

    withTenant($tenant, function () use ($owner, $emp, $user): void {
        $season = Season::factory()->active()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create([
            'created_by' => $owner->id,
            'status' => RiskStatus::Treating,
            'owner_employee_id' => $emp->id,
            'title' => 'retry',
        ]);

        $occ = app(OperationalNotificationOccurrenceRecorder::class)->record(
            'operational_risk',
            (int) $risk->id,
            'assign',
        );
        $hooks = app(NotificationHooks::class);
        $hooks->riskAssigned($risk, $occ);
        $hooks->riskAssigned($risk, $occ);
        $hooks->riskAssigned($risk, $occ);

        expect(Notification::query()
            ->where('type', NotificationType::RiskAssigned)
            ->where('recipient_user_id', $user->id)
            ->count())->toBe(1);
    });
});

it('rolls back notification and occurrence when transaction fails', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assignee = actingAsRiskUserWith(['risks.view'], $tenant);
    $linked = linkEmployeeUser($tenant, $assignee);

    Sanctum::actingAs($owner);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'rollback'], $ctx['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 2, 'impact' => 2], $ctx['header'])->assertOk();
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();
    actingAsRiskUserWith(allRiskPermissions(), $tenant);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctx['header'])->assertOk();

    $beforeNotes = withNotifications($tenant, fn () => Notification::query()->count());
    $beforeOcc = withNotifications($tenant, fn () => OperationalNotificationOccurrence::query()->count());

    try {
        withTenant($tenant, function () use ($risk, $linked): void {
            DB::transaction(function () use ($risk, $linked): void {
                $model = OperationalRisk::query()->lockForUpdate()->findOrFail($risk['id']);
                $model->forceFill([
                    'status' => RiskStatus::Treating->value,
                    'owner_employee_id' => $linked['employee']->id,
                    'assigned_at' => now(),
                    'assigned_by' => auth()->id(),
                ])->save();

                $occ = app(OperationalNotificationOccurrenceRecorder::class)->record(
                    'operational_risk',
                    (int) $model->id,
                    'assign',
                );
                app(NotificationHooks::class)->riskAssigned($model->fresh(['ownerEmployee']), $occ);

                throw new RuntimeException('force rollback');
            });
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(withNotifications($tenant, fn () => Notification::query()->count()))->toBe($beforeNotes)
        ->and(withNotifications($tenant, fn () => OperationalNotificationOccurrence::query()->count()))->toBe($beforeOcc);
});

it('collapses concurrent duplicate delivery attempts for one occurrence', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $user = actingAsRiskUserWith(['risks.view'], $tenant);
    $emp = linkEmployeeUser($tenant, $user)['employee'];

    withTenant($tenant, function () use ($owner, $emp, $user): void {
        $season = Season::factory()->active()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create([
            'created_by' => $owner->id,
            'status' => RiskStatus::Treating,
            'owner_employee_id' => $emp->id,
            'title' => 'concurrent',
        ]);

        $occ = app(OperationalNotificationOccurrenceRecorder::class)->record(
            'operational_risk',
            (int) $risk->id,
            'assign',
        );

        $hooks = app(NotificationHooks::class);
        // Simulate overlapping delivery of the same durable occurrence.
        $hooks->riskAssigned($risk, $occ);
        $hooks->riskAssigned($risk, $occ);

        expect(Notification::query()
            ->where('type', NotificationType::RiskAssigned)
            ->where('recipient_user_id', $user->id)
            ->count())->toBe(1);
    });
});

it('keeps severity alerts tenant scoped', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    $ctxA = riskBootContext();

    $userA = actingAsRiskUserWith(['risks.view', 'risks.approve_assessment'], $tenantA);

    Sanctum::actingAs($ownerA);
    $risk = spaPostJson('/api/v1/risks', ['title' => 'tenant A'], $ctxA['header'])->assertCreated()->json('data');
    spaPostJson('/api/v1/risks/'.$risk['id'].'/assess', ['likelihood' => 5, 'impact' => 5], $ctxA['header'])->assertOk();
    $employee = createRiskEmployee($tenantA);
    spaPutJson('/api/v1/risks/'.$risk['id'].'/treatment', [
        'title' => 'خطة',
        'plan_body' => 'نص',
        'owner_employee_id' => $employee->id,
    ], $ctxA['header'])->assertOk();
    Sanctum::actingAs($userA);
    spaPostJson('/api/v1/risks/'.$risk['id'].'/approve-assessment', [], $ctxA['header'])->assertOk();

    $ownerB = actingAsTenantOwner();
    $tenantB = $ownerB->tenant;

    withNotifications($tenantB, function (): void {
        expect(Notification::query()->where('type', NotificationType::RiskSeverityAlert)->count())->toBe(0);
    });

    withNotifications($tenantA, function () use ($userA): void {
        // Assess enter-band + approve each allocate distinct occurrences.
        expect(Notification::query()
            ->where('type', NotificationType::RiskSeverityAlert)
            ->where('recipient_user_id', $userA->id)
            ->count())->toBe(2);
    });
});

it('does not expose complaint body in notification payloads', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ctx = riskBootContext();

    $assigneeUser = actingAsRiskUserWith(complaintNonSensitivePermissions(), $tenant);
    $linked = linkEmployeeUser($tenant, $assigneeUser);
    $closer = actingAsRiskUserWith(allComplaintPermissions(), $tenant);

    Sanctum::actingAs($owner);
    $secret = 'SECRET_BODY_'.uniqid();
    $complaint = spaPostJson('/api/v1/complaints', [
        'title' => 'خصوصية',
        'body' => $secret,
        'category_code' => 'service',
    ], $ctx['header'])->assertCreated()->json('data');
    spaPatchJson('/api/v1/complaints/'.$complaint['id'], ['start_review' => true], $ctx['header'])->assertOk();
    Sanctum::actingAs($closer);
    spaPostJson('/api/v1/complaints/'.$complaint['id'].'/assign', [
        'assignee_employee_id' => $linked['employee']->id,
    ], $ctx['header'])->assertOk();

    withNotifications($tenant, function () use ($secret): void {
        $notes = Notification::query()->get();
        foreach ($notes as $note) {
            expect($note->body)->not->toContain($secret)
                ->and(json_encode($note->data))->not->toContain($secret);
        }
    });
});
