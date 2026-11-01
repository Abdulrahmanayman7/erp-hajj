<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApprovalMatrixPublishValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * S04 Phase IV WP01-C — publish + validator.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01cPublishTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP01-C tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{tenant: Tenant, actor: User, request: Request}
 */
function ivWp01cContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $actor = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));

    return [
        'tenant' => $tenant,
        'actor' => $actor,
        'request' => Request::create('/purchase-approval-matrices/publish', 'POST'),
    ];
}

/**
 * @param  array{name?: string, levels?: list<array{level_order: int, name: string}>, rules?: list<array<string, mixed>>}  $opts
 */
function ivWp01cSeedDraft(array $ctx, array $opts = []): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx, $opts): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => $opts['name'] ?? 'مصفوفة نشر'],
            $ctx['request'],
        );

        foreach ($opts['levels'] ?? [['level_order' => 1, 'name' => 'L1']] as $level) {
            app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
                $ctx['actor'],
                $matrix,
                $level,
                $ctx['request'],
            );
        }

        foreach ($opts['rules'] ?? [['applies_when_unpriced' => true, 'min_amount' => '0', 'max_amount' => '10000']] as $rule) {
            app(UpsertPurchaseApprovalMatrixRule::class)->execute(
                $ctx['actor'],
                $matrix,
                $rule,
                $ctx['request'],
            );
        }

        return $matrix->fresh(['rules', 'levels']) ?? $matrix;
    });
}

it('publishes a valid draft, assigns version 1, and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01cContext();
    $draft = ivWp01cSeedDraft($ctx);

    $published = withTenant($ctx['tenant'], function () use ($ctx, $draft): PurchaseApprovalMatrix {
        return app(PublishPurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            $draft,
            [],
            $ctx['request'],
        );
    });

    expect($published->status)->toBe(PurchaseApprovalMatrixStatus::Published);
    expect($published->version_no)->toBe(1);
    expect($published->published_by)->toBe($ctx['actor']->id);
    expect($published->published_at)->not->toBeNull();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_PUBLISHED
        && ($e->context['version_no'] ?? null) === 1);
});

it('retires prior published matrix and increments version_no', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp01cContext();

    $first = ivWp01cSeedDraft($ctx, ['name' => 'v1']);
    $publishedFirst = withTenant($ctx['tenant'], fn () => app(PublishPurchaseApprovalMatrix::class)->execute(
        $ctx['actor'],
        $first,
        [],
        $ctx['request'],
    ));
    expect($publishedFirst->version_no)->toBe(1);

    $second = ivWp01cSeedDraft($ctx, ['name' => 'v2']);
    $publishedSecond = withTenant($ctx['tenant'], fn () => app(PublishPurchaseApprovalMatrix::class)->execute(
        $ctx['actor'],
        $second,
        [],
        $ctx['request'],
    ));

    expect($publishedSecond->version_no)->toBe(2);
    expect($publishedSecond->isPublished())->toBeTrue();

    $retired = withTenant($ctx['tenant'], fn () => PurchaseApprovalMatrix::query()->whereKey($publishedFirst->id)->firstOrFail());
    expect($retired->status)->toBe(PurchaseApprovalMatrixStatus::Retired);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::MATRIX_RETIRED
        && ($e->context['entity_id'] ?? null) === $publishedFirst->id);
});

it('rejects republish of already published matrix', function (): void {
    $ctx = ivWp01cContext();
    $draft = ivWp01cSeedDraft($ctx);
    $published = withTenant($ctx['tenant'], fn () => app(PublishPurchaseApprovalMatrix::class)->execute(
        $ctx['actor'],
        $draft,
        [],
        $ctx['request'],
    ));

    withTenant($ctx['tenant'], function () use ($ctx, $published): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute(
                $ctx['actor'],
                $published,
                [],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_ALREADY_PUBLISHED');
        }
    });
});

it('blocks publish without levels', function (): void {
    $ctx = ivWp01cContext();
    $matrix = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $m = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'بلا مستويات'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['actor'],
            $m,
            ['applies_when_unpriced' => true],
            $ctx['request'],
        );

        return $m;
    });

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $matrix, [], $ctx['request']);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_LEVELS_REQUIRED');
        }
    });
});

it('blocks publish without rules', function (): void {
    $ctx = ivWp01cContext();
    $matrix = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $m = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['actor'],
            ['name' => 'بلا قواعد'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['actor'],
            $m,
            ['level_order' => 1, 'name' => 'L1'],
            $ctx['request'],
        );

        return $m;
    });

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $matrix, [], $ctx['request']);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_RULES_REQUIRED');
        }
    });
});

it('blocks publish when level_order is not contiguous 1..N', function (): void {
    $ctx = ivWp01cContext();
    $matrix = ivWp01cSeedDraft($ctx, [
        'levels' => [
            ['level_order' => 1, 'name' => 'L1'],
            ['level_order' => 3, 'name' => 'L3'],
        ],
        'rules' => [['applies_when_unpriced' => true]],
    ]);

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $matrix, [], $ctx['request']);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_LEVELS_INVALID');
        }
    });
});

it('blocks publish without applies_when_unpriced rule', function (): void {
    $ctx = ivWp01cContext();
    $matrix = ivWp01cSeedDraft($ctx, [
        'rules' => [
            [
                'applies_when_unpriced' => false,
                'min_amount' => '0',
                'max_amount' => '1000',
            ],
        ],
    ]);

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $matrix, [], $ctx['request']);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_UNPRICED_RULE_REQUIRED');
        }
    });
});

it('blocks publish when rules are ambiguous at equal specificity', function (): void {
    $ctx = ivWp01cContext();
    $matrix = ivWp01cSeedDraft($ctx, [
        'rules' => [
            [
                'request_kind' => 'administrative',
                'applies_when_unpriced' => true,
                'min_amount' => '0',
                'max_amount' => '1000',
            ],
            [
                'request_kind' => 'administrative',
                'applies_when_unpriced' => true,
                'min_amount' => '500',
                'max_amount' => '1500',
            ],
        ],
    ]);

    withTenant($ctx['tenant'], function () use ($ctx, $matrix): void {
        try {
            app(PublishPurchaseApprovalMatrix::class)->execute($ctx['actor'], $matrix, [], $ctx['request']);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('MATRIX_RULES_AMBIGUOUS');
        }
    });
});

it('allows overlapping bands when specificity differs', function (): void {
    $ctx = ivWp01cContext();
    $matrix = ivWp01cSeedDraft($ctx, [
        'rules' => [
            [
                'request_kind' => 'administrative',
                'applies_when_unpriced' => true,
                'min_amount' => '0',
                // open max → lower band-tightness score
            ],
            [
                'request_kind' => 'administrative',
                'applies_when_unpriced' => true,
                'min_amount' => '0',
                'max_amount' => '1000',
            ],
        ],
    ]);

    $published = withTenant($ctx['tenant'], fn () => app(PublishPurchaseApprovalMatrix::class)->execute(
        $ctx['actor'],
        $matrix,
        [],
        $ctx['request'],
    ));

    expect($published->isPublished())->toBeTrue();
});

it('computes specificity score per publish validator contract', function (): void {
    $rule = new PurchaseApprovalMatrixRule([
        'min_amount' => '10',
        'max_amount' => '20',
        'request_kind' => 'seasonal',
        'applies_when_unpriced' => true,
    ]);
    $rule->forceFill([
        'organization_unit_id' => 5,
        'season_id' => 9,
    ]);

    $score = app(PurchaseApprovalMatrixPublishValidator::class)->specificityScore($rule);
    // request_kind + OU + season + both amounts + unpriced = 5
    expect($score)->toBe(5);
});
