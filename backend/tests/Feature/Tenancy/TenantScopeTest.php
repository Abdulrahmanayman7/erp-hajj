<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantSetting;
use App\Core\Tenancy\PlatformContext;
use Tests\Support\ScopedItem;

beforeEach(function (): void {
    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();
});

test('queries only return rows of the current tenant', function (): void {
    withTenant($this->tenantA, fn () => TenantSetting::factory()->count(3)->create());
    withTenant($this->tenantB, fn () => TenantSetting::factory()->count(2)->create());

    withTenant($this->tenantA, function (): void {
        expect(TenantSetting::query()->count())->toBe(3);

        TenantSetting::query()->get()->each(function (TenantSetting $setting): void {
            expect($setting->tenant_id)->toBe($this->tenantA->id);
        });
    });

    withTenant($this->tenantB, fn () => expect(TenantSetting::query()->count())->toBe(2));
});

test('querying a tenant-owned model without context fails closed', function (): void {
    TenantSetting::query()->count();
})->throws(MissingTenantContextException::class);

test('creating without context fails closed', function (): void {
    TenantSetting::factory()->create();
})->throws(MissingTenantContextException::class);

test('tenant_id is set automatically from context on create', function (): void {
    $setting = withTenant(
        $this->tenantA,
        fn (): TenantSetting => TenantSetting::query()->create(['key' => 'k1', 'value' => ['v' => 1]]),
    );

    expect($setting->tenant_id)->toBe($this->tenantA->id);
});

test('a client-supplied tenant_id attribute is overwritten by the context', function (): void {
    $setting = withTenant($this->tenantA, function (): TenantSetting {
        $model = new TenantSetting(['key' => 'forged', 'value' => ['v' => 1]]);
        $model->tenant_id = $this->tenantB->id; // forged direct assignment
        $model->save();

        return $model;
    });

    expect($setting->tenant_id)->toBe($this->tenantA->id);
});

test('tenant_id in a mass-assignment payload is ignored and force-set', function (): void {
    $setting = withTenant(
        $this->tenantA,
        fn (): TenantSetting => TenantSetting::query()->create([
            'key' => 'mass-forged',
            'value' => ['v' => 1],
            'tenant_id' => $this->tenantB->id, // not fillable → discarded
        ]),
    );

    expect($setting->refresh()->tenant_id)->toBe($this->tenantA->id);
});

test('mutating tenant_id after creation throws', function (): void {
    withTenant($this->tenantA, function (): void {
        $setting = TenantSetting::factory()->create();

        $setting->tenant_id = $this->tenantB->id;
        $setting->save();
    });
})->throws(LogicException::class);

test('finding another tenants record by id returns null', function (): void {
    $foreign = withTenant($this->tenantB, fn (): TenantSetting => TenantSetting::factory()->create());

    $found = withTenant($this->tenantA, fn (): ?TenantSetting => TenantSetting::query()->find($foreign->id));

    expect($found)->toBeNull();
});

test('bulk updates and deletes stay inside the current tenant', function (): void {
    withTenant($this->tenantA, fn () => TenantSetting::factory()->count(2)->create());
    withTenant($this->tenantB, fn () => TenantSetting::factory()->count(2)->create());

    withTenant($this->tenantA, fn () => TenantSetting::query()->delete());

    withTenant($this->tenantA, fn () => expect(TenantSetting::query()->count())->toBe(0));
    withTenant($this->tenantB, fn () => expect(TenantSetting::query()->count())->toBe(2));
});

test('soft-deleted records remain tenant-scoped including withTrashed', function (): void {
    ScopedItem::migrate();

    $item = withTenant($this->tenantA, function (): ScopedItem {
        $item = ScopedItem::query()->create(['name' => 'to-delete']);
        $item->delete();

        return $item;
    });

    // Same tenant: visible via withTrashed, invisible in normal queries.
    withTenant($this->tenantA, function () use ($item): void {
        expect(ScopedItem::query()->find($item->id))->toBeNull()
            ->and(ScopedItem::withTrashed()->find($item->id))->not->toBeNull();
    });

    // Other tenant: invisible even via withTrashed.
    withTenant($this->tenantB, function () use ($item): void {
        expect(ScopedItem::withTrashed()->find($item->id))->toBeNull();
    });
});

test('platform context grants no tenant data access without runAsTenant', function (): void {
    app(PlatformContext::class)->run(function (): void {
        TenantSetting::query()->count();
    });
})->throws(MissingTenantContextException::class);

test('platform code reads tenant data only through runAsTenant per tenant', function (): void {
    withTenant($this->tenantA, fn () => TenantSetting::factory()->count(3)->create());
    withTenant($this->tenantB, fn () => TenantSetting::factory()->count(1)->create());

    $counts = app(PlatformContext::class)->run(function (): array {
        $counts = [];

        // The documented pattern: iterate tenants explicitly, one
        // runAsTenant per tenant — never one global bypass.
        foreach (Tenant::query()->orderBy('id')->cursor() as $tenant) {
            $counts[$tenant->id] = withTenant($tenant, fn (): int => TenantSetting::query()->count());
        }

        return $counts;
    });

    expect($counts[$this->tenantA->id])->toBe(3)
        ->and($counts[$this->tenantB->id])->toBe(1);
});

test('the tenants registry itself is queryable without tenant context', function (): void {
    expect(Tenant::query()->count())->toBe(2);
});

test('the generated SQL qualifies tenant_id with the table name', function (): void {
    $sql = withTenant($this->tenantA, fn (): string => TenantSetting::query()->toSql());

    expect($sql)->toContain('"tenant_settings"."tenant_id"');
});
