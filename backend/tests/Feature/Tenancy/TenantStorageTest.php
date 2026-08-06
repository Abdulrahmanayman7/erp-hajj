<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStorage;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();
    $this->storage = app(TenantStorage::class);
});

test('paths follow the documented tenants/{tenant_id}/{directory} layout', function (): void {
    withTenant($this->tenantA, function (): void {
        expect($this->storage->path(TenantStorage::DOCUMENTS))->toBe("tenants/{$this->tenantA->id}/documents")
            ->and($this->storage->path(TenantStorage::CONTRACTS))->toBe("tenants/{$this->tenantA->id}/contracts")
            ->and($this->storage->path(TenantStorage::EMPLOYEES))->toBe("tenants/{$this->tenantA->id}/employees")
            ->and($this->storage->path(TenantStorage::ASSETS))->toBe("tenants/{$this->tenantA->id}/assets")
            ->and($this->storage->path(TenantStorage::EXPORTS))->toBe("tenants/{$this->tenantA->id}/exports")
            ->and($this->storage->path(TenantStorage::TEMP))->toBe("tenants/{$this->tenantA->id}/temp");
    });
});

test('relative segments are appended inside the tenant namespace', function (): void {
    $path = withTenant(
        $this->tenantA,
        fn (): string => $this->storage->path(TenantStorage::DOCUMENTS, 'contracts/2026/file.pdf'),
    );

    expect($path)->toBe("tenants/{$this->tenantA->id}/documents/contracts/2026/file.pdf");
});

test('an unknown directory is rejected', function (): void {
    withTenant($this->tenantA, fn (): string => $this->storage->path('invoices'));
})->throws(InvalidArgumentException::class);

test('path traversal segments are rejected', function (): void {
    withTenant($this->tenantA, fn (): string => $this->storage->path(TenantStorage::DOCUMENTS, '../2/documents/secret.pdf'));
})->throws(InvalidArgumentException::class);

test('two tenants never share a storage path', function (): void {
    $pathA = withTenant($this->tenantA, fn (): string => $this->storage->path(TenantStorage::DOCUMENTS, 'file.pdf'));
    $pathB = withTenant($this->tenantB, fn (): string => $this->storage->path(TenantStorage::DOCUMENTS, 'file.pdf'));

    expect($pathA)->not->toBe($pathB)
        ->and($pathA)->toStartWith("tenants/{$this->tenantA->id}/")
        ->and($pathB)->toStartWith("tenants/{$this->tenantB->id}/");
});

test('files written through the helper land inside the tenant namespace only', function (): void {
    Storage::fake('local');

    withTenant($this->tenantA, function (): void {
        Storage::disk('local')->put($this->storage->path(TenantStorage::DOCUMENTS, 'a.txt'), 'content-a');
    });

    Storage::disk('local')->assertExists("tenants/{$this->tenantA->id}/documents/a.txt");
    Storage::disk('local')->assertMissing("tenants/{$this->tenantB->id}/documents/a.txt");
});

test('storage paths cannot be generated without a tenant context', function (): void {
    $this->storage->path(TenantStorage::DOCUMENTS);
})->throws(MissingTenantContextException::class);
