<?php

use App\Core\Auth\UserStatus;
use App\Core\Authorization\Actions\BootstrapTenantOwner;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Exceptions\TenantOwnerBootstrapException;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('bootstrap creates first tenant user and assigns tenant_owner', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);

    $tenant = Tenant::factory()->create(['tenant_code' => 'acme']);

    $result = app(BootstrapTenantOwner::class)->execute(
        'acme',
        'Owner Name',
        'Owner@Example.com',
        'SecretPass1',
    );

    expect($result['created_user'])->toBeTrue()
        ->and($result['assigned_owner'])->toBeTrue()
        ->and($result['email'])->toBe('owner@example.com');

    $user = User::query()->where('email', 'owner@example.com')->first();
    expect($user)->not->toBeNull()
        ->and((int) $user->tenant_id)->toBe($tenant->id)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(Hash::check('SecretPass1', $user->password))->toBeTrue()
        ->and(str_contains($user->password, 'SecretPass1'))->toBeFalse();

    withTenant($tenant, function () use ($user): void {
        expect($user->fresh()->hasRole('tenant_owner'))->toBeTrue();
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        if ($e->name !== AuthorizationSecurityEvent::USER_CREATED) {
            return false;
        }

        $encoded = json_encode($e->context);

        return $encoded !== false
            && ! str_contains($encoded, 'SecretPass1')
            && ($e->context['actor'] ?? null) === 'system_bootstrap';
    });
});

test('bootstrap is idempotent for same owner without duplicate pivots', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'idem']);

    $action = app(BootstrapTenantOwner::class);
    $action->execute('idem', 'Owner', 'idem@example.com', 'SecretPass1');
    $second = $action->execute('idem', 'Owner', 'idem@example.com', 'SecretPass1', confirmExistingOwners: true);

    expect($second['already_owner'])->toBeTrue()
        ->and($second['created_user'])->toBeFalse();

    $user = User::query()->where('email', 'idem@example.com')->firstOrFail();
    expect(DB::table('user_roles')->where('user_id', $user->id)->count())->toBe(1);
    expect(User::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('bootstrap requires confirmation when an active owner already exists', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'owned']);
    app(BootstrapTenantOwner::class)->execute('owned', 'First', 'first@example.com', 'SecretPass1');

    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'owned',
        'Second',
        'second@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    $result = app(BootstrapTenantOwner::class)->execute(
        'owned',
        'Second',
        'second@example.com',
        'SecretPass1',
        confirmExistingOwners: true,
    );

    expect($result['created_user'])->toBeTrue()
        ->and($result['assigned_owner'])->toBeTrue();
});

test('bootstrap fails for missing archived suspended tenants and foreign emails', function (): void {
    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'missing-tenant',
        'X',
        'x@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    $archived = Tenant::factory()->archived()->create(['tenant_code' => 'arch']);
    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'arch',
        'X',
        'arch@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    $suspended = Tenant::factory()->suspended()->create(['tenant_code' => 'susp']);
    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'susp',
        'X',
        'susp@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    $tenantA = Tenant::factory()->create(['tenant_code' => 'tena']);
    $tenantB = Tenant::factory()->create(['tenant_code' => 'tenb']);
    tenantUser($tenantA, ['email' => 'shared@example.com']);

    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'tenb',
        'X',
        'shared@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    platformUser(['email' => 'platform@example.com']);
    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'tena',
        'X',
        'platform@example.com',
        'SecretPass1',
    ))->toThrow(TenantOwnerBootstrapException::class);

    unset($archived, $suspended, $tenantB);
});

test('bootstrap can assign owner to existing same-tenant user without overwriting password', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'exist']);
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));

    $user = tenantUser($tenant, [
        'email' => 'existing@example.com',
        'password' => Hash::make('OriginalPass1'),
    ]);

    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'exist',
        'Existing',
        'existing@example.com',
        'UnusedPass99',
    ))->toThrow(TenantOwnerBootstrapException::class);

    $result = app(BootstrapTenantOwner::class)->execute(
        'exist',
        'Existing',
        'existing@example.com',
        'UnusedPass99',
        confirmAssignExistingUser: true,
    );

    expect($result['created_user'])->toBeFalse()
        ->and($result['assigned_owner'])->toBeTrue()
        ->and(Hash::check('OriginalPass1', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('UnusedPass99', $user->fresh()->password))->toBeFalse();
});

test('bootstrap artisan command registers and completes interactively', function (): void {
    Tenant::factory()->create(['tenant_code' => 'cli']);

    $this->artisan('tenant:bootstrap-owner', [
        '--tenant' => 'cli',
        '--name' => 'CLI Owner',
        '--email' => 'cli@example.com',
    ])
        ->expectsQuestion('Password', 'SecretPass1')
        ->expectsQuestion('Confirm password', 'SecretPass1')
        ->assertSuccessful();

    $user = User::query()->where('email', 'cli@example.com')->first();
    expect($user)->not->toBeNull();
    withTenant($user->tenant, fn () => expect($user->fresh()->hasRole('tenant_owner'))->toBeTrue());
});

test('invalid password fails without creating a user', function (): void {
    Tenant::factory()->create(['tenant_code' => 'badpw']);

    expect(fn () => app(BootstrapTenantOwner::class)->execute(
        'badpw',
        'X',
        'badpw@example.com',
        'short',
    ))->toThrow(TenantOwnerBootstrapException::class);

    expect(User::query()->where('email', 'badpw@example.com')->exists())->toBeFalse();
});

test('bootstrap allows pending tenants without changing status', function (): void {
    $tenant = Tenant::factory()->pending()->create(['tenant_code' => 'pend']);

    $result = app(BootstrapTenantOwner::class)->execute(
        'pend',
        'Pending Owner',
        'pend@example.com',
        'SecretPass1',
    );

    expect($result['assigned_owner'])->toBeTrue()
        ->and($tenant->fresh()->status)->toBe(TenantStatus::Pending);
});

test('bootstrap command aborts when existing owners and confirmation declined', function (): void {
    Tenant::factory()->create(['tenant_code' => 'noconfirm']);
    app(BootstrapTenantOwner::class)->execute('noconfirm', 'A', 'a@noconfirm.test', 'SecretPass1');

    $this->artisan('tenant:bootstrap-owner', [
        '--tenant' => 'noconfirm',
        '--name' => 'B',
        '--email' => 'b@noconfirm.test',
    ])
        ->expectsConfirmation('Continue and assign/create another Tenant Owner?', 'no')
        ->assertSuccessful();

    expect(User::query()->where('email', 'b@noconfirm.test')->exists())->toBeFalse();
});
