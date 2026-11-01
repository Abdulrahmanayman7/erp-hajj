<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\DeletePurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\DeletePurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * S04 Phase IV WP01-B — matrix draft Actions (create/update/rules/levels).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01bMatrixDraftTest.php
 */

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP01-B tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{tenant: Tenant, actor: User, request: Request}
 */
function ivWp01bContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $actor = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));

    return [
        'tenant' => $tenant,
        'actor' => $actor,
        'request' => Request::create('/purchase-approval-matrices', 'POST'),
    ];
}

it('creates a draft matrix and audits MATRIX_CREATED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01bContext();

    $matrix = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        return app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'مسودة اعتماد أ'],
            $ctx['request'],
        );
    });

    expect($matrix->status)->toBe(PurchaseApprovalMatrixStatus::Draft);
    expect($matrix->version_no)->toBe(0);
    expect($matrix->name)->toBe('مسودة اعتماد أ');
    expect($matrix->created_by)->toBe($ctx['actor']->id);

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($matrix): bool {
        return $e->name === AuthorizationSecurityEvent::MATRIX_CREATED
            && ($e->context['entity_id'] ?? null) === $matrix->id;
    });
});

it('updates draft name and rejects mutation on published', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01bContext();

    $draft = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        return app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'قبل'],
            $ctx['request'],
        );
    });

    $renamed = withTenant($ctx['tenant'], function () use ($ctx, $draft): PurchaseApprovalMatrix {
        return app(UpdatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            $draft,
            ['name' => 'بعد التحديث'],
            $ctx['request'],
        );
    });
    expect($renamed->name)->toBe('بعد التحديث');

    $matrix = withTenant($ctx['tenant'], function () use ($ctx, $renamed): PurchaseApprovalMatrix {
        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $renamed,
            ['level_order' => 1, 'name' => 'L1'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $renamed,
            ['applies_when_unpriced' => true, 'min_amount' => '0', 'max_amount' => '1000'],
            $ctx['request'],
        );

        return app(PublishPurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            $renamed,
            [],
            $ctx['request'],
        );
    });

    expect($matrix->isPublished())->toBeTrue();

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(UpdatePurchaseApprovalMatrix::class)->execute(
                $ctx['actor'],
                $matrix,
                ['name' => 'محظور'],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_NOT_DRAFT');
        }
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_UPDATED);
});

it('upserts and deletes rules on draft only with amount and FK validation', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01bContext();
    $unit = withTenant($ctx['tenant'], fn () => OrganizationUnit::factory()->create());
    $season = withTenant($ctx['tenant'], fn () => Season::factory()->create());
    $otherTenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $foreignUnit = withTenant($otherTenant, fn () => OrganizationUnit::factory()->create());

    $matrix = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        return app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'قواعد'],
            $ctx['request'],
        );
    });

    $rule = withTenant($ctx['tenant'], function () use ($ctx, $matrix, $unit, $season): PurchaseApprovalMatrixRule {
        return app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $matrix,
            [
                'min_amount' => '100.00',
                'max_amount' => '500.00',
                'request_kind' => 'seasonal',
                'organization_unit_id' => $unit->id,
                'season_id' => $season->id,
                'applies_when_unpriced' => true,
            ],
            $ctx['request'],
        );
    });

    expect($rule->applies_when_unpriced)->toBeTrue();
    expect($rule->organization_unit_id)->toBe($unit->id);
    expect($rule->season_id)->toBe($season->id);
    expect($rule->specificity_hint)->toBeGreaterThan(0);

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(UpsertPurchaseApprovalMatrixRule::class)->execute(
                $ctx['actor'],
                $matrix,
                ['min_amount' => '900', 'max_amount' => '100', 'applies_when_unpriced' => false],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('RULE_AMOUNT_RANGE_INVALID');
        }
    });

    withTenant($ctx['tenant'], function () use ($ctx, $matrix, $foreignUnit): void {
        try {
            app(UpsertPurchaseApprovalMatrixRule::class)->execute(
                $ctx['actor'],
                $matrix,
                [
                    'organization_unit_id' => $foreignUnit->id,
                    'applies_when_unpriced' => true,
                ],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('PR_ORG_UNIT_INVALID');
        }
    });

    $updated = withTenant($ctx['tenant'], function () use ($ctx, $matrix, $rule): PurchaseApprovalMatrixRule {
        return app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $matrix,
            ['max_amount' => '750.00'],
            $ctx['request'],
            $rule,
        );
    });
    expect($updated->max_amount)->toBe('750.00');

    withTenant($ctx['tenant'], function () use ($ctx, $matrix, $updated): void {
        app(DeletePurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $matrix,
            $updated,
            $ctx['request'],
        );
        expect(PurchaseApprovalMatrixRule::query()->whereKey($updated->id)->exists())->toBeFalse();
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_RULE_CREATED);
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_RULE_UPDATED);
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_RULE_DELETED);
});

it('upserts and deletes levels and rejects order conflicts', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01bContext();

    $matrix = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        return app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'مستويات'],
            $ctx['request'],
        );
    });

    $level = withTenant($ctx['tenant'], function () use ($ctx, $matrix): PurchaseApprovalMatrixLevel {
        return app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $matrix,
            ['level_order' => 1, 'name' => 'الأول'],
            $ctx['request'],
        );
    });

    expect($level->level_order)->toBe(1);

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
                $ctx['actor'],
                $matrix,
                ['level_order' => 1, 'name' => 'مكرر'],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('LEVEL_ORDER_CONFLICT');
        }
    });

    $renamed = withTenant($ctx['tenant'], function () use ($ctx, $matrix, $level): PurchaseApprovalMatrixLevel {
        return app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $matrix,
            ['name' => 'مستوى أول محدّث'],
            $ctx['request'],
            $level,
        );
    });
    expect($renamed->name)->toBe('مستوى أول محدّث');

    withTenant($ctx['tenant'], function () use ($ctx, $matrix, $renamed): void {
        app(DeletePurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $matrix,
            $renamed,
            $ctx['request'],
        );
        expect(PurchaseApprovalMatrixLevel::query()->whereKey($renamed->id)->exists())->toBeFalse();
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_LEVEL_CREATED);
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_LEVEL_DELETED);
});

it('denies rule and level writes after publish', function (): void {
    $ctx = ivWp01bContext();

    $published = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $m = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'منشور'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $m,
            ['level_order' => 1, 'name' => 'L1'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $m,
            ['applies_when_unpriced' => true],
            $ctx['request'],
        );

        return app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $m, [], $ctx['request']);
    });

    withTenant($ctx['tenant'], function () use ($ctx, $published): void {
        try {
            app(UpsertPurchaseApprovalMatrixRule::class)->execute(
                $ctx['actor'],
                $published,
                ['applies_when_unpriced' => true, 'min_amount' => '1'],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_NOT_DRAFT');
        }

        try {
            app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
                $ctx['actor'],
                $published,
                ['level_order' => 2, 'name' => 'L2'],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_NOT_DRAFT');
        }
    });
});
