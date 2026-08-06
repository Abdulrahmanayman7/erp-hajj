<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantSetting;
use App\Core\Tenancy\Validation\TenantExists;
use App\Core\Tenancy\Validation\TenantUnique;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();
});

test('TenantExists passes for a record of the current tenant', function (): void {
    $setting = withTenant($this->tenantA, fn (): TenantSetting => TenantSetting::factory()->create());

    $passes = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['setting_id' => $setting->id],
        ['setting_id' => [new TenantExists('tenant_settings')]],
    )->passes());

    expect($passes)->toBeTrue();
});

test('TenantExists fails for another tenants record exactly like a nonexistent one', function (): void {
    $foreign = withTenant($this->tenantB, fn (): TenantSetting => TenantSetting::factory()->create());

    withTenant($this->tenantA, function () use ($foreign): void {
        $crossTenant = Validator::make(
            ['setting_id' => $foreign->id],
            ['setting_id' => [new TenantExists('tenant_settings')]],
        );

        $nonexistent = Validator::make(
            ['setting_id' => 999999],
            ['setting_id' => [new TenantExists('tenant_settings')]],
        );

        expect($crossTenant->fails())->toBeTrue()
            ->and($nonexistent->fails())->toBeTrue()
            ->and($crossTenant->errors()->toArray())->toBe($nonexistent->errors()->toArray());
    });
});

test('TenantExists supports a custom column', function (): void {
    withTenant($this->tenantA, fn () => TenantSetting::query()->create(['key' => 'by-key', 'value' => ['v' => 1]]));

    $passes = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['key' => 'by-key'],
        ['key' => [new TenantExists('tenant_settings', 'key')]],
    )->passes());

    expect($passes)->toBeTrue();
});

test('TenantUnique fails for a duplicate inside the current tenant', function (): void {
    withTenant($this->tenantA, fn () => TenantSetting::query()->create(['key' => 'taken', 'value' => ['v' => 1]]));

    $fails = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['key' => 'taken'],
        ['key' => [new TenantUnique('tenant_settings')]],
    )->fails());

    expect($fails)->toBeTrue();
});

test('TenantUnique passes when the value exists only in another tenant', function (): void {
    withTenant($this->tenantB, fn () => TenantSetting::query()->create(['key' => 'taken-elsewhere', 'value' => ['v' => 1]]));

    $passes = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['key' => 'taken-elsewhere'],
        ['key' => [new TenantUnique('tenant_settings')]],
    )->passes());

    expect($passes)->toBeTrue();
});

test('TenantUnique ignore allows updating the record itself', function (): void {
    $setting = withTenant($this->tenantA, fn (): TenantSetting => TenantSetting::query()->create(['key' => 'mine', 'value' => ['v' => 1]]));

    $passes = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['key' => 'mine'],
        ['key' => [(new TenantUnique('tenant_settings'))->ignore($setting->id)]],
    )->passes());

    expect($passes)->toBeTrue();
});

test('TenantUnique ignore with another tenants record id cannot skip the check', function (): void {
    // The value is taken inside tenant A; the attacker passes the id of a
    // tenant-B record hoping the ignore clause hides the conflict.
    withTenant($this->tenantA, fn () => TenantSetting::query()->create(['key' => 'contested', 'value' => ['v' => 1]]));
    $foreign = withTenant($this->tenantB, fn (): TenantSetting => TenantSetting::query()->create(['key' => 'contested', 'value' => ['v' => 2]]));

    $fails = withTenant($this->tenantA, fn (): bool => Validator::make(
        ['key' => 'contested'],
        ['key' => [(new TenantUnique('tenant_settings'))->ignore($foreign->id)]],
    )->fails());

    expect($fails)->toBeTrue();
});

test('TenantExists without a tenant context fails closed', function (): void {
    Validator::make(
        ['setting_id' => 1],
        ['setting_id' => [new TenantExists('tenant_settings')]],
    )->passes();
})->throws(MissingTenantContextException::class);

test('TenantUnique without a tenant context fails closed', function (): void {
    Validator::make(
        ['key' => 'anything'],
        ['key' => [new TenantUnique('tenant_settings')]],
    )->passes();
})->throws(MissingTenantContextException::class);
