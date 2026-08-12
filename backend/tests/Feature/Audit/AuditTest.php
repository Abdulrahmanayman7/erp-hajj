<?php

use App\Core\Audit\AuditRecorder;
use App\Core\Audit\SensitiveFieldSanitizer;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    app(CorrelationId::class)->set('test-corr-'.uniqid());
});

test('audit schema exists without updated_at', function (): void {
    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(Schema::hasColumn('audit_logs', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('audit_logs', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('audit_logs', 'correlation_id'))->toBeTrue();
});

test('audit_logs.view required for list and show', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = actingAsTenantOwner($tenant);

    $log = withTenant($tenant, fn () => AuditLog::record([
        'tenant_id' => $tenant->id,
        'context_type' => 'tenant',
        'actor_type' => 'user',
        'actor_user_id' => $owner->id,
        'actor_label' => $owner->name,
        'event_type' => 'TASK_ASSIGNED',
        'entity_type' => 'task',
        'entity_id' => 1,
        'entity_number' => 'TSK-000001',
        'entity_label' => 'Test',
        'reason' => null,
        'metadata' => null,
        'before_values' => null,
        'after_values' => null,
        'ip_address' => null,
        'user_agent' => null,
        'correlation_id' => 'corr-1',
        'source' => 'http',
        'created_at' => now(),
    ]));

    $this->getJson('/api/v1/audit-logs')->assertOk();
    $this->getJson('/api/v1/audit-logs/'.$log->id)->assertOk();

    $employee = tenantUser($tenant);
    assignRole($employee, 'employee');
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/audit-logs')->assertForbidden();
    $this->getJson('/api/v1/audit-logs/'.$log->id)->assertForbidden();
});

test('auditor general_manager and owner receive audit_logs.view', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);

    foreach (['tenant_owner', 'general_manager', 'auditor'] as $code) {
        $user = tenantUser($tenant);
        assignRole($user, $code);
        expect($user->fresh()->hasPermission('audit_logs.view'))->toBeTrue();
    }

    $employee = tenantUser($tenant);
    assignRole($employee, 'employee');
    expect($employee->fresh()->hasPermission('audit_logs.view'))->toBeFalse();
});

test('cross-tenant list and show isolation', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = actingAsTenantOwner($tenantA);
    actingAsTenantOwner($tenantB);

    $logB = withTenant($tenantB, fn () => AuditLog::record([
        'tenant_id' => $tenantB->id,
        'context_type' => 'tenant',
        'actor_type' => 'system',
        'actor_user_id' => null,
        'actor_label' => 'النظام',
        'event_type' => 'TASK_CREATED',
        'entity_type' => null,
        'entity_id' => null,
        'entity_number' => null,
        'entity_label' => null,
        'reason' => null,
        'metadata' => null,
        'before_values' => null,
        'after_values' => null,
        'ip_address' => null,
        'user_agent' => null,
        'correlation_id' => 'corr-b',
        'source' => 'console',
        'created_at' => now(),
    ]));

    Sanctum::actingAs($ownerA);
    $response = $this->getJson('/api/v1/audit-logs')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($logB->id);

    $this->getJson('/api/v1/audit-logs/'.$logB->id)->assertNotFound();
});

test('authorization security event persists one audit row and keeps log listener', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = actingAsTenantOwner($tenant);

    Log::spy();

    withTenant($tenant, function () use ($owner, $tenant): void {
        app(AuthorizationSecurity::class)->record(AuthorizationSecurityEvent::TASK_ASSIGNED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $owner->id,
            'task_id' => 44,
            'task_number' => 'TSK-000044',
            'task_title' => 'تجهيز العقود',
            'from_status' => 'draft',
            'to_status' => 'assigned',
        ], request());
    });

    expect(AuditLog::query()->where('tenant_id', $tenant->id)->where('event_type', 'TASK_ASSIGNED')->count())->toBe(1);

    $row = AuditLog::query()->where('event_type', 'TASK_ASSIGNED')->first();
    expect($row->entity_type)->toBe('task')
        ->and($row->entity_number)->toBe('TSK-000044')
        ->and($row->before_values)->toMatchArray(['status' => 'draft'])
        ->and($row->after_values)->toMatchArray(['status' => 'assigned'])
        ->and($row->actor_user_id)->toBe($owner->id)
        ->and($row->correlation_id)->not->toBeEmpty();

    Log::shouldHaveReceived('info')->withArgs(fn ($message): bool => $message === 'authorization.security_event');
});

test('sanitizer strips secrets and storage paths', function (): void {
    $sanitizer = app(SensitiveFieldSanitizer::class);
    $clean = $sanitizer->sanitize([
        'password' => 'secret',
        'token' => 'abc',
        'storage_path' => 'tenants/1/documents/x.pdf',
        'document_id' => 9,
        'title' => 'عقد',
    ]);

    expect($clean)->toHaveKey('document_id')
        ->and($clean)->toHaveKey('title')
        ->and($clean)->not->toHaveKey('password')
        ->and($clean)->not->toHaveKey('token')
        ->and($clean)->not->toHaveKey('storage_path');
});

test('append-only model rejects update and delete and no write routes', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsTenantOwner($tenant);

    $log = withTenant($tenant, fn () => AuditLog::record([
        'tenant_id' => $tenant->id,
        'context_type' => 'tenant',
        'actor_type' => 'system',
        'actor_user_id' => null,
        'actor_label' => 'النظام',
        'event_type' => 'LOGIN_SUCCESS',
        'entity_type' => null,
        'entity_id' => null,
        'entity_number' => null,
        'entity_label' => null,
        'reason' => null,
        'metadata' => null,
        'before_values' => null,
        'after_values' => null,
        'ip_address' => null,
        'user_agent' => null,
        'correlation_id' => 'corr-imm',
        'source' => 'http',
        'created_at' => now(),
    ]));

    expect(fn () => $log->update(['event_type' => 'HACKED']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);

    $this->patchJson('/api/v1/audit-logs/'.$log->id, [])->assertStatus(405);
    $this->deleteJson('/api/v1/audit-logs/'.$log->id)->assertStatus(405);
    $this->postJson('/api/v1/audit-logs', [])->assertStatus(405);
});

test('rolled back transaction leaves no audit row', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsTenantOwner($tenant);

    try {
        withTenant($tenant, function () use ($tenant): void {
            DB::transaction(function () use ($tenant): void {
                app(AuditRecorder::class)->recordAuthorizationEvent('CONTRACT_APPROVED', [
                    'tenant_id' => $tenant->id,
                    'actor_id' => auth()->id(),
                    'contract_id' => 1,
                    'contract_number' => 'CNT-000001',
                    'from_status' => 'in_review',
                    'to_status' => 'approved',
                ]);
                throw new RuntimeException('force rollback');
            });
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(AuditLog::query()->where('event_type', 'CONTRACT_APPROVED')->count())->toBe(0);
});

test('authorization audit failure fails closed for domain event', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = actingAsTenantOwner($tenant);

    $this->mock(AuditRecorder::class, function ($mock): void {
        $mock->shouldReceive('recordAuthorizationEvent')
            ->andThrow(new RuntimeException('audit unavailable'));
        $mock->shouldReceive('recordAuthEvent')->andReturn(null);
    });

    expect(fn () => withTenant($tenant, function () use ($tenant, $owner): void {
        app(AuthorizationSecurity::class)->record(AuthorizationSecurityEvent::CONTRACT_CREATED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $owner->id,
            'contract_id' => 99,
        ]);
    }))->toThrow(RuntimeException::class);

    expect(AuditLog::query()->where('event_type', 'CONTRACT_CREATED')->count())->toBe(0);
});

test('filters search event actor entity dates and correlation', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = actingAsTenantOwner($tenant);

    withTenant($tenant, function () use ($tenant, $owner): void {
        AuditLog::record([
            'tenant_id' => $tenant->id,
            'context_type' => 'tenant',
            'actor_type' => 'user',
            'actor_user_id' => $owner->id,
            'actor_label' => 'أحمد',
            'event_type' => 'DOCUMENT_DOWNLOADED',
            'entity_type' => 'document',
            'entity_id' => 7,
            'entity_number' => 'DOC-000007',
            'entity_label' => 'ملف',
            'reason' => null,
            'metadata' => ['mime_type' => 'application/pdf'],
            'before_values' => null,
            'after_values' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'correlation_id' => 'corr-filter-1',
            'source' => 'http',
            'created_at' => now()->subDay(),
        ]);
        AuditLog::record([
            'tenant_id' => $tenant->id,
            'context_type' => 'tenant',
            'actor_type' => 'system',
            'actor_user_id' => null,
            'actor_label' => 'النظام',
            'event_type' => 'STOCK_TRANSFERRED',
            'entity_type' => 'inventory_item',
            'entity_id' => 3,
            'entity_number' => 'ITM-000003',
            'entity_label' => 'ماء',
            'reason' => null,
            'metadata' => null,
            'before_values' => null,
            'after_values' => null,
            'ip_address' => null,
            'user_agent' => null,
            'correlation_id' => 'corr-other',
            'source' => 'job',
            'created_at' => now(),
        ]);
    });

    $this->getJson('/api/v1/audit-logs?event_type=DOCUMENT_DOWNLOADED')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event_type', 'DOCUMENT_DOWNLOADED')
        ->assertJsonMissingPath('data.0.metadata');

    $this->getJson('/api/v1/audit-logs?search=DOC-000007')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/audit-logs?actor_user_id='.$owner->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/audit-logs?entity_type=document&entity_id=7')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/audit-logs?correlation_id=corr-filter-1')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $detail = $this->getJson('/api/v1/audit-logs/'.AuditLog::query()->where('event_type', 'DOCUMENT_DOWNLOADED')->value('id'))
        ->assertOk()
        ->json('data');

    expect($detail)->toHaveKeys(['metadata', 'ip_address', 'user_agent', 'deep_link'])
        ->and($detail['metadata'])->not->toHaveKey('storage_path');
});

test('deleted actor snapshot remains readable', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = actingAsTenantOwner($tenant);
    $victim = tenantUser($tenant, ['name' => 'مستخدم محذوف', 'email' => 'victim-'.uniqid().'@example.com']);
    $email = $victim->email;

    $log = withTenant($tenant, fn () => AuditLog::record([
        'tenant_id' => $tenant->id,
        'context_type' => 'tenant',
        'actor_type' => 'user',
        'actor_user_id' => $victim->id,
        'actor_label' => 'مستخدم محذوف <'.$email.'>',
        'event_type' => 'USER_UPDATED',
        'entity_type' => 'user',
        'entity_id' => $victim->id,
        'entity_number' => null,
        'entity_label' => $victim->name,
        'reason' => null,
        'metadata' => null,
        'before_values' => null,
        'after_values' => null,
        'ip_address' => null,
        'user_agent' => null,
        'correlation_id' => 'corr-del',
        'source' => 'http',
        'created_at' => now(),
    ]));

    $victim->delete();

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/audit-logs/'.$log->fresh()->id)
        ->assertOk()
        ->assertJsonPath('data.actor_label', 'مستخدم محذوف <'.$email.'>')
        ->assertJsonPath('data.actor_user_id', null);
});

test('auth audit persistence failure does not undo login', function (): void {
    $tenant = Tenant::factory()->create();
    $user = provisionTenantRbac($tenant);
    $user->forceFill([
        'email' => 'audit-login@example.com',
        'password' => Hash::make('password123'),
    ])->save();

    $this->mock(AuditRecorder::class, function ($mock): void {
        $mock->shouldReceive('recordAuthEvent')->andReturnUsing(function () {
            Log::critical('audit.auth_persist_failed', ['event' => 'LOGIN_SUCCESS']);

            return null;
        });
        $mock->shouldReceive('recordAuthorizationEvent')->andReturnNull();
    });

    spaPostJson('/api/v1/auth/login', [
        'email' => 'audit-login@example.com',
        'password' => 'password123',
        'remember' => false,
    ])->assertOk();
});

test('list defaults to newest first with pagination meta', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsTenantOwner($tenant);

    withTenant($tenant, function () use ($tenant): void {
        foreach (range(1, 3) as $i) {
            AuditLog::record([
                'tenant_id' => $tenant->id,
                'context_type' => 'tenant',
                'actor_type' => 'system',
                'actor_user_id' => null,
                'actor_label' => 'النظام',
                'event_type' => 'MEETING_CREATED',
                'entity_type' => null,
                'entity_id' => null,
                'entity_number' => null,
                'entity_label' => null,
                'reason' => null,
                'metadata' => null,
                'before_values' => null,
                'after_values' => null,
                'ip_address' => null,
                'user_agent' => null,
                'correlation_id' => 'corr-p-'.$i,
                'source' => 'http',
                'created_at' => now()->subMinutes(3 - $i),
            ]);
        }
    });

    $this->getJson('/api/v1/audit-logs?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(2, 'data');
});
