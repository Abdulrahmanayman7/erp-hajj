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
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP06 — cancel_approved (IV-D13 / D3).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp06CancelApprovedTest.php
 */

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP06 cancel-approved tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: User,
 *   approver: User,
 *   reviewer: User,
 *   admin: User,
 *   canceller: User,
 *   request: Request
 * }
 */
function ivWp06Context(): array
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

    $reviewer = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.financial_review',
    ], $tenant);

    $admin = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.manage_approval_config',
    ], $tenant);

    $canceller = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.cancel_approved',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'creator' => $creator,
        'approver' => $approver,
        'reviewer' => $reviewer,
        'admin' => $admin,
        'canceller' => $canceller,
        'request' => Request::create('/purchase-requests/cancel-approved', 'POST'),
    ];
}

function ivWp06PublishMatrix(array $ctx): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة إلغاء معتمد'],
            $ctx['request'],
        );

        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['admin'],
            $matrix,
            ['level_order' => 1, 'name' => 'اعتماد وحيد'],
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

function ivWp06GrantAuthority(array $ctx, User $subject): void
{
    withTenant($ctx['tenant'], function () use ($ctx, $subject): void {
        app(GrantPurchaseApproverAuthority::class)->execute(
            $ctx['admin'],
            [
                'user_id' => $subject->id,
                'max_amount' => '50000.00',
                'covers_unpriced' => true,
                'effective_from' => now()->subHour()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });
}

function ivWp06ApprovedPr(array $ctx): PurchaseRequest
{
    $suffix = uniqid('wp06_', true);
    $payload = [
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب إلغاء معتمد '.$suffix,
        'purpose' => 'اختبار إلغاء طلب شراء معتمد '.$suffix,
        'priority' => 'normal',
        'urgency_justification' => null,
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [[
            'line_no' => 1,
            'description' => 'بند '.$suffix,
            'quantity' => 5,
            'unit' => 'قطعة',
            'need_date' => now()->addDays(10)->toDateString(),
            'unit_estimate' => 200,
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

        $pr = PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
        expect($pr->status)->toBe(PurchaseRequestStatus::UnderReview);

        app(RecordPurchaseRequestFinancialReview::class)->execute(
            $ctx['reviewer'],
            $pr,
            [
                'outcome' => 'cleared',
                'justification' => 'مراجعة مالية للاعتماد النهائي قبل الإلغاء',
                'version_no' => (int) $pr->version_no,
            ],
            $ctx['request'],
        );

        app(ApprovePurchaseRequestStep::class)->execute(
            $ctx['approver'],
            $pr->fresh() ?? $pr,
            [],
            $ctx['request'],
        );

        $approved = PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
        expect($approved->status)->toBe(PurchaseRequestStatus::Approved);

        return $approved;
    });
}

it('cancels approved PR with reason and audits PR_CANCEL_APPROVED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp06Context();
    ivWp06PublishMatrix($ctx);
    ivWp06GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp06ApprovedPr($ctx);

    Sanctum::actingAs($ctx['canceller']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'إلغاء معتمد بعد مراجعة الاحتياج التشغيلي',
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::Cancelled)
        ->and($fresh->cancelled_by)->toBe($ctx['canceller']->id)
        ->and($fresh->cancelled_at)->not->toBeNull()
        ->and($fresh->cancellation_reason)->toBe('إلغاء معتمد بعد مراجعة الاحتياج التشغيلي');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_CANCEL_APPROVED,
    );
});

it('blocks cancel-approved when procurement_engaged_at is set', function (): void {
    $ctx = ivWp06Context();
    ivWp06PublishMatrix($ctx);
    ivWp06GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp06ApprovedPr($ctx);

    withTenant($ctx['tenant'], function () use ($pr): void {
        $pr->forceFill([
            'procurement_engaged_at' => now(),
            'procurement_engagement_ref' => 'FUTURE-RFQ-1',
        ])->save();
    });

    Sanctum::actingAs($ctx['canceller']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'محاولة إلغاء بعد بدء الشراء اللاحق',
    ])->assertStatus(422)->assertJsonPath('code', 'PR_PROCUREMENT_ENGAGED');

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::Approved);
});

it('rejects cancel-approved when not approved or reason too short or missing perm', function (): void {
    $ctx = ivWp06Context();
    ivWp06PublishMatrix($ctx);
    ivWp06GrantAuthority($ctx, $ctx['approver']);

    $suffix = uniqid('wp06draft_', true);
    $draft = withTenant($ctx['tenant'], function () use ($ctx, $suffix): PurchaseRequest {
        return app(CreatePurchaseRequest::class)->execute($ctx['creator'], [
            'request_kind' => 'administrative',
            'title' => 'مسودة '.$suffix,
            'purpose' => 'غرض المسودة للاختبار '.$suffix,
            'priority' => 'normal',
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'currency' => 'SAR',
            'lines' => [[
                'line_no' => 1,
                'description' => 'بند',
                'quantity' => 1,
                'unit' => 'قطعة',
                'need_date' => now()->addDays(5)->toDateString(),
                'unit_estimate' => 10,
            ]],
        ], $ctx['request']);
    });

    Sanctum::actingAs($ctx['canceller']);
    $this->postJson('/api/v1/purchase-requests/'.$draft->id.'/cancel-approved', [
        'reason' => 'سبب كافٍ للإلغاء لكنه على مسودة غير معتمدة',
    ])->assertStatus(409)->assertJsonPath('code', 'PR_INVALID_TRANSITION');

    $pr = ivWp06ApprovedPr($ctx);

    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'قصير',
    ])->assertStatus(422);

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'محاولة إلغاء دون صلاحية cancel_approved',
    ])->assertForbidden();
});

it('returns 404 on cross-tenant cancel-approved', function (): void {
    $ctx = ivWp06Context();
    ivWp06PublishMatrix($ctx);
    ivWp06GrantAuthority($ctx, $ctx['approver']);
    $pr = ivWp06ApprovedPr($ctx);

    $other = Tenant::factory()->create();
    provisionTenantRbac($other);
    $intruder = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.cancel_approved',
    ], $other);

    Sanctum::actingAs($intruder);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'محاولة وصول عبر منشأة أخرى للإلغاء المعتمد',
    ])->assertNotFound();
});
