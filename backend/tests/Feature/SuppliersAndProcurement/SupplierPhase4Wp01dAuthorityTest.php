<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\RevokePurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * S04 Phase IV WP01-D — financial authority Actions + catalog/sensitive flags.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01dAuthorityTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP01-D tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{tenant: Tenant, actor: User, subject: User, unit: OrganizationUnit, request: Request}
 */
function ivWp01dContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $actor = createSupplierUserWith(['purchase_requests.manage_approval_config'], $tenant);
    $subject = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    return [
        'tenant' => $tenant,
        'actor' => $actor,
        'subject' => $subject,
        'unit' => $unit,
        'request' => Request::create('/purchase-approver-authorities', 'POST'),
    ];
}

it('grants authority with covers_unpriced and audits AUTHORITY_GRANTED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01dContext();

    $authority = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApproverAuthority {
        return app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            [
                'user_id' => $ctx['subject']->id,
                'max_amount' => '50000.00',
                'covers_unpriced' => true,
                'effective_from' => now()->toIso8601String(),
                'organization_unit_id' => $ctx['unit']->id,
            ],
            $ctx['request'],
        );
    });

    expect($authority->covers_unpriced)->toBeTrue()
        ->and((string) $authority->max_amount)->toBe('50000.00')
        ->and($authority->organization_unit_id)->toBe($ctx['unit']->id)
        ->and($authority->isRevoked())->toBeFalse();

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($authority): bool {
        return $e->name === AuthorizationSecurityEvent::AUTHORITY_GRANTED
            && (int) ($e->context['entity_id'] ?? 0) === (int) $authority->id;
    });
});

it('rejects overlapping active authority windows for the same user', function (): void {
    $ctx = ivWp01dContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            [
                'user_id' => $ctx['subject']->id,
                'max_amount' => '10000',
                'covers_unpriced' => false,
                'effective_from' => now()->subDay()->toIso8601String(),
                'effective_to' => now()->addDays(10)->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    withTenant($ctx['tenant'], function () use ($ctx): void {
        try {
            app(GrantPurchaseApproverAuthority::class)->execute(
                $ctx['actor'],
                [
                    'user_id' => $ctx['subject']->id,
                    'max_amount' => '20000',
                    'covers_unpriced' => true,
                    'effective_from' => now()->toIso8601String(),
                    'effective_to' => null,
                ],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('AUTHORITY_OVERLAP');
        }
    });
});

it('rejects cross-tenant user and non-positive amount', function (): void {
    $ctx = ivWp01dContext();
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $foreign = withTenant($other, fn () => User::factory()->create(['tenant_id' => $other->id]));

    withTenant($ctx['tenant'], function () use ($ctx, $foreign): void {
        try {
            app(GrantPurchaseApproverAuthority::class)->execute(
                $ctx['actor'],
                [
                    'user_id' => $foreign->id,
                    'max_amount' => '1000',
                    'covers_unpriced' => false,
                    'effective_from' => now()->toIso8601String(),
                ],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('AUTHORITY_USER_INVALID');
        }
    });

    withTenant($ctx['tenant'], function () use ($ctx): void {
        try {
            app(GrantPurchaseApproverAuthority::class)->execute(
                $ctx['actor'],
                [
                    'user_id' => $ctx['subject']->id,
                    'max_amount' => '0',
                    'covers_unpriced' => false,
                    'effective_from' => now()->toIso8601String(),
                ],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('AUTHORITY_AMOUNT_INVALID');
        }
    });
});

it('updates and revokes authority with audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01dContext();

    $authority = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApproverAuthority {
        return app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            [
                'user_id' => $ctx['subject']->id,
                'max_amount' => '15000',
                'covers_unpriced' => false,
                'effective_from' => now()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    $updated = withTenant($ctx['tenant'], function () use ($ctx, $authority): PurchaseApproverAuthority {
        return app(UpdatePurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            $authority,
            [
                'max_amount' => '25000.50',
                'covers_unpriced' => true,
            ],
            $ctx['request'],
        );
    });

    expect((string) $updated->max_amount)->toBe('25000.50')
        ->and($updated->covers_unpriced)->toBeTrue();

    $revoked = withTenant($ctx['tenant'], function () use ($ctx, $updated): PurchaseApproverAuthority {
        return app(RevokePurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            $updated,
            $ctx['request'],
        );
    });

    expect($revoked->isRevoked())->toBeTrue();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::AUTHORITY_UPDATED);
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::AUTHORITY_REVOKED);

    // After revoke, a new non-overlapping grant for the same user is allowed.
    $replacement = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApproverAuthority {
        return app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            [
                'user_id' => $ctx['subject']->id,
                'max_amount' => '9000',
                'covers_unpriced' => false,
                'effective_from' => now()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });
    expect($replacement->isRevoked())->toBeFalse();
});

it('registers Phase IV purchase_requests sensitive permissions without template grants', function (): void {
    $perms = [
        'purchase_requests.manage_approval_config',
        'purchase_requests.approve',
        'purchase_requests.reject',
        'purchase_requests.return',
        'purchase_requests.cancel_approved',
        'purchase_requests.manage_delegations',
    ];

    foreach ($perms as $permission) {
        expect(PermissionCatalog::allNames())->toContain($permission)
            ->and(PermissionCatalog::sensitiveExplicitOnlyPermissions())->toContain($permission)
            ->and(PermissionCatalog::highRiskPermissions())->toContain($permission);

        foreach (PermissionCatalog::roleTemplates() as $template) {
            expect($template['permissions'])->not->toContain($permission);
        }
    }
});

it('hides authority across tenants (tenant scope)', function (): void {
    $ctx = ivWp01dContext();
    $authority = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApproverAuthority {
        return app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['actor'],
            [
                'user_id' => $ctx['subject']->id,
                'max_amount' => '1000',
                'covers_unpriced' => false,
                'effective_from' => now()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    withTenant($other, function () use ($authority): void {
        expect(PurchaseApproverAuthority::query()->whereKey($authority->id)->exists())->toBeFalse();
    });
});
