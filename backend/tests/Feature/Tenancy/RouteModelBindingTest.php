<?php

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantSetting;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ScopedItem;

beforeEach(function (): void {
    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();

    Route::middleware(['api', 'auth:sanctum', 'tenant.active'])
        ->get('/api/test/settings/{setting}', fn (TenantSetting $setting) => ApiResponse::success([
            'id' => $setting->id,
            'tenant_id' => $setting->tenant_id,
        ]));

    Route::middleware(['api', 'auth:sanctum', 'tenant.active'])
        ->get('/api/test/items/{item}', fn (ScopedItem $item) => ApiResponse::success([
            'id' => $item->id,
        ]));
});

test('binding resolves a record of the callers own tenant', function (): void {
    $setting = withTenant($this->tenantA, fn (): TenantSetting => TenantSetting::factory()->create());
    Sanctum::actingAs(tenantUser($this->tenantA));

    $this->getJson("/api/test/settings/{$setting->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $setting->id)
        ->assertJsonPath('data.tenant_id', $this->tenantA->id);
});

test('cross-tenant binding returns 404 — indistinguishable from nonexistent', function (): void {
    $foreign = withTenant($this->tenantB, fn (): TenantSetting => TenantSetting::factory()->create());
    Sanctum::actingAs(tenantUser($this->tenantA));

    $crossTenant = $this->getJson("/api/test/settings/{$foreign->id}")->assertNotFound();
    $nonexistent = $this->getJson('/api/test/settings/999999')->assertNotFound();

    // Enumeration defense: both responses must have the same shape.
    expect($crossTenant->json())->toBe($nonexistent->json());
});

test('binding without authentication returns 401', function (): void {
    $setting = withTenant($this->tenantA, fn (): TenantSetting => TenantSetting::factory()->create());

    $this->getJson("/api/test/settings/{$setting->id}")->assertUnauthorized();
});

test('a platform user binding a tenant-owned model is blocked with 403 before any query', function (): void {
    $setting = withTenant($this->tenantA, fn (): TenantSetting => TenantSetting::factory()->create());
    Sanctum::actingAs(platformUser());

    $this->getJson("/api/test/settings/{$setting->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_CONTEXT_MISSING');
});

test('a suspended tenant user is blocked before binding executes', function (): void {
    $suspended = Tenant::factory()->suspended()->create();
    $setting = withTenant($suspended, fn (): TenantSetting => TenantSetting::factory()->create());
    Sanctum::actingAs(tenantUser($suspended));

    $this->getJson("/api/test/settings/{$setting->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});

test('a soft-deleted record returns 404 through binding', function (): void {
    ScopedItem::migrate();

    $item = withTenant($this->tenantA, function (): ScopedItem {
        $item = ScopedItem::query()->create(['name' => 'gone']);
        $item->delete();

        return $item;
    });

    Sanctum::actingAs(tenantUser($this->tenantA));

    $this->getJson("/api/test/items/{$item->id}")->assertNotFound();
});
