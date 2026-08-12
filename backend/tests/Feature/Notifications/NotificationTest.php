<?php

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Exceptions\NotificationDomainException;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationDedupe;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

function createNotificationFor(User $user, array $attrs = []): Notification
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, function () use ($user, $attrs): Notification {
        return Notification::factory()->forRecipient($user)->create($attrs);
    });
}

// ─── DATABASE ────────────────────────────────────────────────────────────────

test('notifications table has required columns and unique dedupe index', function (): void {
    expect(Schema::hasTable('notifications'))->toBeTrue();

    foreach ([
        'tenant_id',
        'recipient_user_id',
        'type',
        'title',
        'body',
        'severity',
        'entity_type',
        'entity_id',
        'dedupe_key',
        'read_at',
        'correlation_id',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('notifications', $column))->toBeTrue();
    }

    $indexNames = collect(Schema::getIndexes('notifications'))->pluck('name');
    expect($indexNames->contains('notifications_tenant_dedupe_unique'))->toBeTrue();
});

test('notification content is immutable except read_at', function (): void {
    $owner = actingAsTenantOwner();
    $notification = createNotificationFor($owner, ['title' => 'أصل']);

    try {
        withTenant($owner->tenant, function () use ($notification): void {
            $notification->title = 'مخترق';
            $notification->save();
        });
        expect(false)->toBeTrue();
    } catch (NotificationDomainException $e) {
        expect($e->errorCode())->toBe('NOTIFICATION_IMMUTABLE');
    }

    $notification->refresh();
    expect($notification->title)->toBe('أصل');

    withTenant($owner->tenant, function () use ($notification): void {
        $notification->read_at = now();
        $notification->save();
    });

    expect($notification->fresh()->read_at)->not->toBeNull();
});

// ─── API OWNERSHIP / TENANCY ─────────────────────────────────────────────────

test('recipient can list show mark-read and unread-count', function (): void {
    $owner = actingAsTenantOwner();
    $notification = createNotificationFor($owner, [
        'type' => NotificationType::TaskAssigned,
        'severity' => NotificationSeverity::Info,
        'title' => 'مهمة',
    ]);

    spaGetJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.id', $notification->id);

    spaGetJson('/api/v1/notifications/unread-count')->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    spaGetJson("/api/v1/notifications/{$notification->id}")->assertOk()
        ->assertJsonPath('data.is_read', false);

    spaPostJson("/api/v1/notifications/{$notification->id}/read")->assertOk()
        ->assertJsonPath('data.is_read', true);

    spaGetJson('/api/v1/notifications/unread-count')->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    spaPostJson("/api/v1/notifications/{$notification->id}/read")->assertOk()
        ->assertJsonPath('data.is_read', true);
});

test('same-tenant other user cannot access notification (404)', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $notification = createNotificationFor($owner);

    $other = withTenant($tenant, fn () => User::factory()->forTenant($tenant)->create());
    Sanctum::actingAs($other);

    spaGetJson("/api/v1/notifications/{$notification->id}")->assertNotFound();
    spaPostJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
});

test('cross-tenant notification access returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $notification = createNotificationFor($ownerA);

    $ownerB = actingAsTenantOwner();
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/notifications/{$notification->id}")->assertNotFound();
    spaGetJson('/api/v1/notifications')->assertOk()
        ->assertJsonCount(0, 'data');
});

test('mark-all-read only affects current recipient', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    createNotificationFor($owner);
    createNotificationFor($owner);

    $other = withTenant($tenant, fn () => User::factory()->forTenant($tenant)->create());
    $otherNotification = createNotificationFor($other);

    spaPostJson('/api/v1/notifications/read-all')->assertOk()
        ->assertJsonPath('data.updated_count', 2);

    withTenant($tenant, function () use ($owner, $otherNotification): void {
        expect(Notification::query()->where('recipient_user_id', $owner->id)->whereNull('read_at')->count())->toBe(0);
        expect($otherNotification->fresh()->read_at)->toBeNull();
    });
});

test('list filters unread type severity and sorts newest first', function (): void {
    $owner = actingAsTenantOwner();

    $older = createNotificationFor($owner, [
        'type' => NotificationType::TaskAssigned,
        'severity' => NotificationSeverity::Info,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $newer = createNotificationFor($owner, [
        'type' => NotificationType::TaskOverdue,
        'severity' => NotificationSeverity::Critical,
    ]);
    withTenant($owner->tenant, function () use ($older): void {
        $older->read_at = now();
        $older->save();
    });

    spaGetJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.id', $newer->id);

    spaGetJson('/api/v1/notifications?unread_only=1')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $newer->id);

    spaGetJson('/api/v1/notifications?type=TASK_OVERDUE')->assertOk()
        ->assertJsonCount(1, 'data');

    spaGetJson('/api/v1/notifications?severity=critical')->assertOk()
        ->assertJsonCount(1, 'data');
});

test('no public create update delete notification endpoints', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/notifications', ['title' => 'x'])->assertStatus(405);
    spaPatchJson('/api/v1/notifications/1', ['title' => 'x'])->assertStatus(405);
    spaDeleteJson('/api/v1/notifications/1')->assertStatus(405);
});

// ─── DEDUPE / RECIPIENTS ─────────────────────────────────────────────────────

test('dispatcher dedupe prevents duplicate logical notifications', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    withTenant($tenant, function () use ($owner): void {
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->notify(
            type: NotificationType::TaskDueSoon,
            recipientUsers: [$owner],
            entityType: 'task',
            entityId: 42,
            dedupeBucket: '2026-08-12',
            context: ['number' => 'TSK-1'],
            allowQueue: false,
        );
        $dispatcher->notify(
            type: NotificationType::TaskDueSoon,
            recipientUsers: [$owner],
            entityType: 'task',
            entityId: 42,
            dedupeBucket: '2026-08-12',
            context: ['number' => 'TSK-1'],
            allowQueue: false,
        );

        expect(Notification::query()->where('recipient_user_id', $owner->id)->count())->toBe(1);
    });
});

test('employee without user and disabled user are skipped', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    withTenant($tenant, function () use ($tenant): void {
        $unit = OrganizationUnit::factory()->create();
        $employeeNoUser = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => null,
        ]);

        $disabled = User::factory()->forTenant($tenant)->create([
            'status' => UserStatus::Disabled,
        ]);
        $employeeDisabled = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => $disabled->id,
        ]);

        $resolver = app(NotificationRecipientResolver::class);
        expect($resolver->resolveUserFromEmployeeId($employeeNoUser->id))->toBeNull();
        expect($resolver->resolveUserFromEmployeeId($employeeDisabled->id))->toBeNull();
    });
});

test('dedupe key format matches documented pattern', function (): void {
    $key = NotificationDedupe::buildKey(
        NotificationType::StockBelowMinimum,
        'inventory_item',
        9,
        3,
        '1:2026-08-12',
    );

    expect($key)->toBe('STOCK_BELOW_MINIMUM:inventory_item:9:3:1:2026-08-12');
});

test('task assign creates notification for linked assignee user after commit', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $assigneeUser = withTenant($tenant, fn () => User::factory()->forTenant($tenant)->create());

    [$taskId, $employeeId] = withTenant($tenant, function () use ($owner, $assigneeUser): array {
        $unit = OrganizationUnit::factory()->create();
        $employee = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => $assigneeUser->id,
        ]);
        $task = Task::factory()->create([
            'created_by' => $owner->id,
            'organization_unit_id' => $unit->id,
        ]);

        return [$task->id, $employee->id];
    });

    spaPutJson("/api/v1/tasks/{$taskId}/assignee", [
        'assigned_to_employee_id' => $employeeId,
    ])->assertOk();

    Sanctum::actingAs($assigneeUser);

    spaGetJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.type', 'TASK_ASSIGNED')
        ->assertJsonPath('data.0.entity_type', 'task')
        ->assertJsonPath('data.0.entity_id', $taskId);
});

test('scanner scan-tasks is idempotent for due-soon', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $timezone = $tenant->timezone ?: 'Asia/Riyadh';

    withTenant($tenant, function () use ($owner, $timezone): void {
        $unit = OrganizationUnit::factory()->create();
        $employee = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => $owner->id,
        ]);
        Task::factory()->create([
            'created_by' => $owner->id,
            'organization_unit_id' => $unit->id,
            'assigned_to_employee_id' => $employee->id,
            'status' => TaskStatus::Assigned,
            'due_date' => now($timezone)->toDateString(),
        ]);
    });

    $this->artisan('notifications:scan-tasks')->assertSuccessful();
    $this->artisan('notifications:scan-tasks')->assertSuccessful();

    withTenant($tenant, function () use ($owner): void {
        expect(
            Notification::query()
                ->where('recipient_user_id', $owner->id)
                ->where('type', NotificationType::TaskDueSoon->value)
                ->count()
        )->toBe(1);
    });
});
