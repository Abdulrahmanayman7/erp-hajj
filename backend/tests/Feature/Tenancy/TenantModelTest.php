<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantSetting;
use App\Core\Tenancy\TenantStatus;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a tenant is created with documented defaults', function (): void {
    $tenant = Tenant::query()->create([
        'tenant_code' => 'first-org',
        'name' => 'منشأة الاختبار',
    ]);

    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Pending)
        ->and($tenant->locale)->toBe('ar')
        ->and($tenant->timezone)->toBe('Asia/Riyadh')
        ->and($tenant->suspended_at)->toBeNull()
        ->and($tenant->archived_at)->toBeNull();
});

test('tenant_code is always stored lowercase', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'MiXeD-Case-99']);

    expect($tenant->tenant_code)->toBe('mixed-case-99');
});

test('tenant_code is globally unique', function (): void {
    Tenant::factory()->create(['tenant_code' => 'duplicate-code']);
    Tenant::factory()->create(['tenant_code' => 'duplicate-code']);
})->throws(QueryException::class);

test('tenant name is globally unique', function (): void {
    Tenant::factory()->create(['name' => 'منشأة مكررة']);
    Tenant::factory()->create(['name' => 'منشأة مكررة']);
})->throws(QueryException::class);

test('tenant_code is immutable after creation', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'original-code']);

    $tenant->tenant_code = 'new-code';
    $tenant->save();
})->throws(LogicException::class);

test('tenant name can change without affecting tenant_code', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'stable-code', 'name' => 'الاسم القديم']);

    $tenant->update(['name' => 'الاسم الجديد']);

    expect($tenant->refresh())
        ->name->toBe('الاسم الجديد')
        ->tenant_code->toBe('stable-code');
});

test('status is cast to the TenantStatus enum', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($tenant->status->isActive())->toBeFalse()
        ->and($tenant->suspended_at)->not->toBeNull();
});

test('status transition matrix matches the documented lifecycle', function (): void {
    // pending → active, archived (not suspended)
    expect(TenantStatus::Pending->canTransitionTo(TenantStatus::Active))->toBeTrue()
        ->and(TenantStatus::Pending->canTransitionTo(TenantStatus::Archived))->toBeTrue()
        ->and(TenantStatus::Pending->canTransitionTo(TenantStatus::Suspended))->toBeFalse();

    // active → suspended, archived
    expect(TenantStatus::Active->canTransitionTo(TenantStatus::Suspended))->toBeTrue()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Archived))->toBeTrue()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Pending))->toBeFalse();

    // suspended → active, archived
    expect(TenantStatus::Suspended->canTransitionTo(TenantStatus::Active))->toBeTrue()
        ->and(TenantStatus::Suspended->canTransitionTo(TenantStatus::Archived))->toBeTrue();

    // archived is terminal
    foreach (TenantStatus::cases() as $target) {
        expect(TenantStatus::Archived->canTransitionTo($target))->toBeFalse();
    }
});

test('tenants table has no soft deletes column', function (): void {
    expect(Schema::hasColumn('tenants', 'deleted_at'))->toBeFalse();
});

test('the first tenant rafee is seeded exactly as documented', function (): void {
    $this->seed(TenantSeeder::class);

    $tenant = Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();

    expect($tenant->name)->toBe('رفيع')
        ->and($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->locale)->toBe('ar')
        ->and($tenant->timezone)->toBe('Asia/Riyadh');
});

test('the tenant seeder is idempotent', function (): void {
    $this->seed(TenantSeeder::class);
    $this->seed(TenantSeeder::class);

    expect(Tenant::query()->where('tenant_code', 'rafee')->count())->toBe(1);
});

test('tenant has users and settings relations and user belongs to tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $user = tenantUser($tenant);
    withTenant($tenant, fn () => TenantSetting::factory()->count(2)->create());

    expect($tenant->users)->toHaveCount(1)
        ->and($tenant->users->first()->is($user))->toBeTrue()
        ->and($user->tenant->is($tenant))->toBeTrue()
        ->and($user->isPlatformUser())->toBeFalse();

    // TenantSetting is tenant-owned: even the Tenant->settings relation is
    // governed by the fail-closed scope and requires a context.
    $settings = withTenant($tenant, fn () => $tenant->settings()->get());

    expect($settings)->toHaveCount(2);
});

test('a user without tenant_id is a platform user', function (): void {
    $user = platformUser();

    expect($user->tenant_id)->toBeNull()
        ->and($user->isPlatformUser())->toBeTrue()
        ->and($user->tenant)->toBeNull();
});

test('a tenant with users cannot be hard-deleted (ON DELETE RESTRICT)', function (): void {
    $tenant = Tenant::factory()->create();
    tenantUser($tenant);

    $tenant->delete();
})->throws(QueryException::class);

test('same setting key may exist in two tenants but not twice in one tenant', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create();

    withTenant($a, fn () => TenantSetting::query()->create(['key' => 'shared_key', 'value' => ['v' => 1]]));
    withTenant($b, fn () => TenantSetting::query()->create(['key' => 'shared_key', 'value' => ['v' => 2]]));

    // Duplicate inside the same tenant violates UNIQUE (tenant_id, key).
    expect(fn () => withTenant($a, fn () => TenantSetting::query()->create(['key' => 'shared_key', 'value' => ['v' => 3]])))
        ->toThrow(QueryException::class);
});
