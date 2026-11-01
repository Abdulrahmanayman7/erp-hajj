<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\RoutePurchaseRequestApproval;
use App\Modules\SuppliersAndProcurement\Actions\SubmitPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Jobs\RoutePurchaseRequestApprovalJob;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP02 — routing engine (afterCommit job, instances, IV-D6 blocks).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp02RoutingTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP02 routing tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: User,
 *   approver: User,
 *   admin: User,
 *   request: Request
 * }
 */
function ivWp02Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $creator = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
    ], $tenant);

    $approver = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $tenant);

    $admin = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.manage_approval_config',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'creator' => $creator,
        'approver' => $approver,
        'admin' => $admin,
        'request' => Request::create('/purchase-requests/route', 'POST'),
    ];
}

function ivWp02PublishMatrix(array $ctx, array $extraRules = []): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx, $extraRules): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة توجيه'],
            $ctx['request'],
        );

        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['admin'],
            $matrix,
            ['level_order' => 1, 'name' => 'اعتماد أول'],
            $ctx['request'],
        );

        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['admin'],
            $matrix,
            ['level_order' => 2, 'name' => 'اعتماد نهائي'],
            $ctx['request'],
        );

        // Publish requires ≥1 unpriced-path rule.
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['admin'],
            $matrix,
            [
                'applies_when_unpriced' => true,
                'min_amount' => null,
                'max_amount' => null,
            ],
            $ctx['request'],
        );

        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['admin'],
            $matrix,
            [
                'applies_when_unpriced' => false,
                'min_amount' => '0',
                'max_amount' => '100000',
            ],
            $ctx['request'],
        );

        foreach ($extraRules as $rule) {
            app(UpsertPurchaseApprovalMatrixRule::class)->execute(
                $ctx['admin'],
                $matrix,
                $rule,
                $ctx['request'],
            );
        }

        return app(PublishPurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            $matrix,
            [],
            $ctx['request'],
        );
    });
}

function ivWp02GrantAuthority(array $ctx, User $subject, string $maxAmount = '50000.00', bool $coversUnpriced = true): void
{
    withTenant($ctx['tenant'], function () use ($ctx, $subject, $maxAmount, $coversUnpriced): void {
        app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['admin'],
            [
                'user_id' => $subject->id,
                'max_amount' => $maxAmount,
                'covers_unpriced' => $coversUnpriced,
                'effective_from' => now()->subHour()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });
}

/**
 * @return array{pr: PurchaseRequest, payload: array<string, mixed>}
 */
function ivWp02CreateDraft(array $ctx, array $lineOverrides = []): array
{
    $payload = [
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب توريد للاختبار',
        'purpose' => 'اختبار محرك التوجيه مرحلة رابعة',
        'priority' => 'normal',
        'urgency_justification' => null,
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [
            array_merge([
                'line_no' => 1,
                'description' => 'مستلزم تشغيل',
                'quantity' => 10,
                'unit' => 'قطعة',
                'need_date' => now()->addDays(14)->toDateString(),
                'unit_estimate' => 100,
            ], $lineOverrides),
        ],
    ];

    $pr = withTenant($ctx['tenant'], function () use ($ctx, $payload): PurchaseRequest {
        return app(CreatePurchaseRequest::class)->execute(
            $ctx['creator'],
            $payload,
            $ctx['request'],
        );
    });

    return ['pr' => $pr, 'payload' => $payload];
}

it('routes a submitted PR to under_review with instance and pending steps', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp02Context();
    ivWp02PublishMatrix($ctx);
    ivWp02GrantAuthority($ctx, $ctx['approver']);

    $created = ivWp02CreateDraft($ctx);
    $pr = $created['pr'];

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            [],
            $ctx['request'],
        );
    });

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());

    expect($fresh->status)->toBe(PurchaseRequestStatus::UnderReview)
        ->and($fresh->matrix_id)->not->toBeNull()
        ->and($fresh->matrix_version_no)->toBe(1)
        ->and($fresh->routing_blocked_at)->toBeNull()
        ->and($fresh->routing_block_code)->toBeNull();

    $instance = withTenant($ctx['tenant'], function () use ($pr): PurchaseRequestApprovalInstance {
        return PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $pr->id)
            ->where('version_no', 1)
            ->with('steps')
            ->firstOrFail();
    });

    expect($instance->status)->toBe(PurchaseRequestApprovalInstanceStatus::Active)
        ->and($instance->steps)->toHaveCount(2)
        ->and($instance->steps->every(
            fn ($s): bool => $s->status === PurchaseRequestApprovalStepStatus::Pending,
        ))->toBeTrue()
        ->and($instance->steps->pluck('level_order')->all())->toBe([1, 2]);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_ROUTED,
    );
});

it('stays submitted with PR_MATRIX_MISSING when no published matrix exists', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp02Context();
    ivWp02GrantAuthority($ctx, $ctx['approver']);

    $created = ivWp02CreateDraft($ctx);
    $pr = $created['pr'];

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            [],
            $ctx['request'],
        );
    });

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());

    expect($fresh->status)->toBe(PurchaseRequestStatus::Submitted)
        ->and($fresh->routing_block_code)->toBe('PR_MATRIX_MISSING')
        ->and($fresh->routing_blocked_at)->not->toBeNull()
        ->and($fresh->matrix_id)->toBeNull();

    $count = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->count());
    expect($count)->toBe(0);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_ROUTING_BLOCKED
            && ($e->context['routing_block_code'] ?? null) === 'PR_MATRIX_MISSING',
    );
});

it('is idempotent when route is invoked twice for the same version', function (): void {
    $ctx = ivWp02Context();
    ivWp02PublishMatrix($ctx);
    ivWp02GrantAuthority($ctx, $ctx['approver']);

    $created = ivWp02CreateDraft($ctx);
    $pr = $created['pr'];

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        // Submit with queue sync would route once; force submitted then dual-route like concurrent jobs.
        $submitted = app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            [],
            $ctx['request'],
        );

        expect($submitted->fresh()->status)->toBe(PurchaseRequestStatus::UnderReview);

        app(RoutePurchaseRequestApproval::class)->execute((int) $pr->id, 1);
        app(RoutePurchaseRequestApproval::class)->execute((int) $pr->id, 1);
        RoutePurchaseRequestApprovalJob::dispatchSync((int) $pr->id, 1, 'test-dual-route');
    });

    $count = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->where('version_no', 1)
        ->count());

    expect($count)->toBe(1);

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::UnderReview);
});

it('blocks routing when creator is the sole authority holder (SoD exclude)', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp02Context();

    // Creator also holds approve — but is the only authority holder.
    $creatorApprover = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
        'purchase_requests.approve',
    ], $ctx['tenant']);
    $ctx['creator'] = $creatorApprover;

    ivWp02PublishMatrix($ctx);
    ivWp02GrantAuthority($ctx, $creatorApprover);

    $created = ivWp02CreateDraft($ctx);
    $pr = $created['pr'];

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            [],
            $ctx['request'],
        );
    });

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());

    expect($fresh->status)->toBe(PurchaseRequestStatus::Submitted)
        ->and($fresh->routing_block_code)->toBe('PR_ROUTING_BLOCKED')
        ->and($fresh->routing_blocked_at)->not->toBeNull();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_ROUTING_BLOCKED
            && ($e->context['routing_block_code'] ?? null) === 'PR_ROUTING_BLOCKED',
    );
});

it('allows manual POST route by manage_approval_config after config is fixed', function (): void {
    $ctx = ivWp02Context();
    ivWp02GrantAuthority($ctx, $ctx['approver']);

    $created = ivWp02CreateDraft($ctx);
    $pr = $created['pr'];

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            [],
            $ctx['request'],
        );
    });

    $blocked = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($blocked->status)->toBe(PurchaseRequestStatus::Submitted)
        ->and($blocked->routing_block_code)->toBe('PR_MATRIX_MISSING');

    ivWp02PublishMatrix($ctx);

    Sanctum::actingAs($ctx['admin']);
    $response = $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/route');
    $response->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.routing_block_code', null);

    Sanctum::actingAs($ctx['creator']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/route')->assertForbidden();
});
