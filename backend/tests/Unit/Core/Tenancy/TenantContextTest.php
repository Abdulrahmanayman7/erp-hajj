<?php

use App\Core\Tenancy\Exceptions\ConflictingTenantContextException;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;

function makeTenant(int $id): Tenant
{
    return (new Tenant)->forceFill(['id' => $id, 'tenant_code' => "tenant-{$id}", 'name' => "Tenant {$id}"]);
}

beforeEach(function (): void {
    $this->context = new TenantContext;
});

test('set then get returns the same tenant', function (): void {
    $tenant = makeTenant(1);

    $this->context->set($tenant);

    expect($this->context->get())->toBe($tenant)
        ->and($this->context->id())->toBe(1)
        ->and($this->context->has())->toBeTrue();
});

test('get and id return null and has is false without context', function (): void {
    expect($this->context->get())->toBeNull()
        ->and($this->context->id())->toBeNull()
        ->and($this->context->has())->toBeFalse();
});

test('require returns the tenant when context is set', function (): void {
    $tenant = makeTenant(1);
    $this->context->set($tenant);

    expect($this->context->require())->toBe($tenant);
});

test('require throws MissingTenantContextException without context', function (): void {
    $this->context->require();
})->throws(MissingTenantContextException::class);

test('setting a different tenant over an existing context throws', function (): void {
    $this->context->set(makeTenant(1));
    $this->context->set(makeTenant(2));
})->throws(ConflictingTenantContextException::class);

test('setting the same tenant twice is idempotent', function (): void {
    $tenant = makeTenant(1);

    $this->context->set($tenant);
    $this->context->set($tenant);

    expect($this->context->id())->toBe(1);
});

test('clear removes the context', function (): void {
    $this->context->set(makeTenant(1));
    $this->context->clear();

    expect($this->context->has())->toBeFalse();
});

test('runAsTenant sets the tenant inside the callback and restores nothing after', function (): void {
    $tenant = makeTenant(1);

    $observed = $this->context->runAsTenant($tenant, fn (): ?int => $this->context->id());

    expect($observed)->toBe(1)
        ->and($this->context->has())->toBeFalse();
});

test('runAsTenant restores the previous tenant after the callback', function (): void {
    $outer = makeTenant(1);
    $inner = makeTenant(2);

    $this->context->set($outer);

    $this->context->runAsTenant($inner, function () use ($inner): void {
        expect($this->context->get())->toBe($inner);
    });

    expect($this->context->get())->toBe($outer);
});

test('runAsTenant restores the previous context when the callback throws', function (): void {
    $outer = makeTenant(1);
    $this->context->set($outer);

    try {
        $this->context->runAsTenant(makeTenant(2), function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->context->get())->toBe($outer);
});

test('nested runAsTenant restores each level correctly', function (): void {
    $a = makeTenant(1);
    $b = makeTenant(2);

    $this->context->runAsTenant($a, function () use ($b): void {
        $this->context->runAsTenant($b, function (): void {
            expect($this->context->id())->toBe(2);
        });

        expect($this->context->id())->toBe(1);
    });

    expect($this->context->has())->toBeFalse();
});
