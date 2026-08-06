<?php

use App\Core\Tenancy\Exceptions\UnauthorizedPlatformContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\PlatformContext;
use App\Core\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenantContext = new TenantContext;
    $this->platform = new PlatformContext($this->tenantContext);
});

test('run activates the platform context only inside the callback', function (): void {
    expect($this->platform->isActive())->toBeFalse();

    $this->platform->run(function (): void {
        expect($this->platform->isActive())->toBeTrue();
    });

    expect($this->platform->isActive())->toBeFalse();
});

test('run deactivates the platform context when the callback throws', function (): void {
    try {
        $this->platform->run(function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->platform->isActive())->toBeFalse();
});

test('run returns the callback result', function (): void {
    expect($this->platform->run(fn (): string => 'result'))->toBe('result');
});

test('platform context cannot be entered while a tenant context is set', function (): void {
    $tenant = (new Tenant)->forceFill(['id' => 1]);
    $this->tenantContext->set($tenant);

    $this->platform->run(fn () => null);
})->throws(UnauthorizedPlatformContextException::class);

test('tenant context may be entered inside a platform context via runAsTenant', function (): void {
    $tenant = (new Tenant)->forceFill(['id' => 7]);

    $observed = $this->platform->run(
        fn (): ?int => $this->tenantContext->runAsTenant($tenant, fn (): ?int => $this->tenantContext->id()),
    );

    expect($observed)->toBe(7)
        ->and($this->tenantContext->has())->toBeFalse()
        ->and($this->platform->isActive())->toBeFalse();
});
