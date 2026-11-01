<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\ApprovePurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\RecordPurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Actions\SubmitPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP03 — approve / reject / return / resubmit / concurrent step claim.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp03ApprovalDecisionsTest.php
 */

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP03 approval tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: User,
 *   approver: User,
 *   approver2: User,
 *   reviewer: User,
 *   admin: User,
 *   request: Request
 * }
 */
function ivWp03Context(): array
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
        'purchase_requests.reject',
        'purchase_requests.return',
    ], $tenant);

    $approver2 = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
        'purchase_requests.reject',
        'purchase_requests.return',
    ], $tenant);

    $reviewer = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.financial_review',
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
        'approver2' => $approver2,
        'reviewer' => $reviewer,
        'admin' => $admin,
        'request' => Request::create('/purchase-requests/decide', 'POST'),
    ];
}

function ivWp03PublishMatrix(array $ctx): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة قرارات الاعتماد'],
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

        return app(PublishPurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            $matrix,
            [],
            $ctx['request'],
        );
    });
}

function ivWp03GrantAuthority(array $ctx, User $subject, string $maxAmount = '50000.00'): void
{
    withTenant($ctx['tenant'], function () use ($ctx, $subject, $maxAmount): void {
        app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['admin'],
            [
                'user_id' => $subject->id,
                'max_amount' => $maxAmount,
                'covers_unpriced' => true,
                'effective_from' => now()->subHour()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });
}

/**
 * @return PurchaseRequest under_review with pending steps (fresh from DB after sync route job)
 */
function ivWp03RoutedPr(array $ctx, ?string $titleSuffix = null): PurchaseRequest
{
    $suffix = $titleSuffix ?? uniqid('wp03_', true);
    $payload = [
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب اعتماد للاختبار '.$suffix,
        'purpose' => 'اختبار قرارات الاعتماد مرحلة رابعة '.$suffix,
        'priority' => 'normal',
        'urgency_justification' => null,
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [[
            'line_no' => 1,
            'description' => 'مستلزم تشغيل '.$suffix,
            'quantity' => 10,
            'unit' => 'قطعة',
            'need_date' => now()->addDays(14)->toDateString(),
            'unit_estimate' => 100,
        ]],
    ];

    return withTenant($ctx['tenant'], function () use ($ctx, $payload): PurchaseRequest {
        $pr = app(CreatePurchaseRequest::class)->execute($ctx['creator'], $payload, $ctx['request']);
        app(SubmitPurchaseRequest::class)->execute(
            $ctx['creator'],
            $pr,
            ['acknowledge_duplicate_warning' => true],
            $ctx['request'],
        );

        $fresh = PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
        expect($fresh->status)->toBe(PurchaseRequestStatus::UnderReview);

        return $fresh;
    });
}

function ivWp03RecordFr(array $ctx, PurchaseRequest $pr, ?User $reviewer = null): void
{
    $reviewer ??= $ctx['reviewer'];
    withTenant($ctx['tenant'], function () use ($ctx, $pr, $reviewer): void {
        app(RecordPurchaseRequestFinancialReview::class)->execute(
            $reviewer,
            $pr->fresh() ?? $pr,
            [
                'outcome' => 'cleared',
                'justification' => 'المراجعة المالية مكتملة للاعتماد النهائي',
                'version_no' => (int) $pr->version_no,
            ],
            $ctx['request'],
        );
    });
}

it('approves through levels to final approved with FR gate', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp03Context();
    ivWp03PublishMatrix($ctx);
    ivWp03GrantAuthority($ctx, $ctx['approver']);
    ivWp03GrantAuthority($ctx, $ctx['approver2']);

    $pr = ivWp03RoutedPr($ctx);
    expect($pr->status)->toBe(PurchaseRequestStatus::UnderReview);

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'under_review');

    $steps = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalStep::query()
        ->whereHas('instance', fn ($q) => $q->where('purchase_request_id', $pr->id)->where('version_no', 1))
        ->orderBy('level_order')
        ->get());
    expect($steps[0]->status)->toBe(PurchaseRequestApprovalStepStatus::Approved)
        ->and($steps[1]->status)->toBe(PurchaseRequestApprovalStepStatus::Pending);

    ivWp03RecordFr($ctx, $pr);

    Sanctum::actingAs($ctx['approver2']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $instance = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->where('version_no', 1)
        ->firstOrFail());
    expect($instance->status)->toBe(PurchaseRequestApprovalInstanceStatus::CompletedApproved);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_APPROVED_STEP,
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_APPROVED,
    );
});

it('rejects on active step and cancels remaining pending steps', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp03Context();
    ivWp03PublishMatrix($ctx);
    ivWp03GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp03RoutedPr($ctx);

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/reject', [
        'reason' => 'رفض نهائي بسبب عدم اكتمال المواصفات المطلوبة',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');

    $instance = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->with('steps')
        ->firstOrFail());

    expect($instance->status)->toBe(PurchaseRequestApprovalInstanceStatus::CompletedRejected);

    $byStatus = $instance->steps->groupBy(fn ($s) => $s->status->value);
    expect($byStatus->get('rejected'))->toHaveCount(1)
        ->and($byStatus->get('cancelled'))->toHaveCount(1);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_REJECTED,
    );
});

it('returns for revision then resubmit creates a new approval instance', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp03Context();
    ivWp03PublishMatrix($ctx);
    ivWp03GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp03RoutedPr($ctx);

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/return', [
        'reason' => 'إعادة للتعديل لاستكمال البنود الناقصة',
    ])->assertOk()->assertJsonPath('data.status', 'returned');

    $v1 = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->where('version_no', 1)
        ->firstOrFail());
    expect($v1->status)->toBe(PurchaseRequestApprovalInstanceStatus::CompletedReturned);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_RETURNED,
    );

    Sanctum::actingAs($ctx['creator']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/submit', [
        'acknowledge_duplicate_warning' => true,
    ])->assertOk()
        ->assertJsonPath('data.version_no', 2);

    $afterResubmit = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($afterResubmit->status)->toBe(PurchaseRequestStatus::UnderReview)
        ->and($afterResubmit->version_no)->toBe(2);

    $instances = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalInstance::query()
        ->where('purchase_request_id', $pr->id)
        ->orderBy('version_no')
        ->get());

    expect($instances)->toHaveCount(2)
        ->and($instances[0]->status)->toBe(PurchaseRequestApprovalInstanceStatus::CompletedReturned)
        ->and($instances[1]->version_no)->toBe(2)
        ->and($instances[1]->status)->toBe(PurchaseRequestApprovalInstanceStatus::Active);
});

it('lets only one concurrent approve win; loser gets 409', function (): void {
    $ctx = ivWp03Context();
    // Single final level so both actors contend for the same step.
    withTenant($ctx['tenant'], function () use ($ctx): void {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة مستوى واحد'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['admin'],
            $matrix,
            ['level_order' => 1, 'name' => 'اعتماد نهائي'],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['admin'],
            $matrix,
            ['applies_when_unpriced' => true, 'min_amount' => null, 'max_amount' => null],
            $ctx['request'],
        );
        app(UpsertPurchaseApprovalMatrixRule::class)->execute(
            $ctx['admin'],
            $matrix,
            ['applies_when_unpriced' => false, 'min_amount' => '0', 'max_amount' => '100000'],
            $ctx['request'],
        );
        app(PublishPurchaseApprovalMatrix::class)->execute($ctx['admin'], $matrix, [], $ctx['request']);
    });
    ivWp03GrantAuthority($ctx, $ctx['approver']);
    ivWp03GrantAuthority($ctx, $ctx['approver2']);

    $pr = ivWp03RoutedPr($ctx);
    ivWp03RecordFr($ctx, $pr);

    $codes = withTenant($ctx['tenant'], function () use ($ctx, $pr): array {
        $out = [];
        foreach ([$ctx['approver'], $ctx['approver2']] as $actor) {
            try {
                app(ApprovePurchaseRequestStep::class)->execute(
                    $actor,
                    $pr->fresh() ?? $pr,
                    [],
                    $ctx['request'],
                );
                $out[] = 'ok';
            } catch (\App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException $e) {
                $out[] = $e->errorCode();
            }
        }

        return $out;
    });

    expect($codes)->toContain('ok')
        ->and(collect($codes)->intersect(['PR_STEP_NOT_PENDING', 'PR_CONCURRENT_DECISION'])->count())->toBe(1);

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::Approved);

    // HTTP: second decision after terminal reject → PR_CONCURRENT_DECISION.
    $pr2 = ivWp03RoutedPr($ctx);
    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr2->id.'/reject', [
        'reason' => 'رفض متزامن لاختبار الخطوة غير المعلّقة',
    ])->assertOk();
    Sanctum::actingAs($ctx['approver2']);
    $this->postJson('/api/v1/purchase-requests/'.$pr2->id.'/reject', [
        'reason' => 'محاولة رفض ثانية بعد اكتمال القرار',
    ])->assertStatus(409)->assertJsonPath('code', 'PR_CONCURRENT_DECISION');
});
