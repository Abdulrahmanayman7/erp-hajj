<?php

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
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Support\FinalApprovalGate;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP04 — financial review during under_review + final SoD gates.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp04FinancialSodTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP04 FR/SoD tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: User,
 *   approver: User,
 *   frApprover: User,
 *   reviewerOnly: User,
 *   admin: User,
 *   request: Request
 * }
 */
function ivWp04Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $creator = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
        'purchase_requests.approve',
    ], $tenant);

    $approver = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $tenant);

    // Holds approve + FR — may review, must not final-approve.
    $frApprover = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
        'purchase_requests.financial_review',
    ], $tenant);

    $reviewerOnly = createSupplierUserWith([
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
        'frApprover' => $frApprover,
        'reviewerOnly' => $reviewerOnly,
        'admin' => $admin,
        'request' => Request::create('/purchase-requests/fr-sod', 'POST'),
    ];
}

function ivWp04PublishMatrix(array $ctx, int $levels = 2): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx, $levels): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة فصل المهام'],
            $ctx['request'],
        );

        for ($i = 1; $i <= $levels; $i++) {
            app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
                $ctx['admin'],
                $matrix,
                ['level_order' => $i, 'name' => 'مستوى '.$i],
                $ctx['request'],
            );
        }

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

function ivWp04GrantAuthority(array $ctx, User $subject): void
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

function ivWp04RoutedPr(array $ctx, ?string $titleSuffix = null): PurchaseRequest
{
    $suffix = $titleSuffix ?? uniqid('wp04_', true);
    $payload = [
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب فصل مهام مالي '.$suffix,
        'purpose' => 'اختبار بوابة المراجعة المالية والاعتماد النهائي '.$suffix,
        'priority' => 'normal',
        'urgency_justification' => null,
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [[
            'line_no' => 1,
            'description' => 'مستلزم تشغيل '.$suffix,
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

        $fresh = PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
        expect($fresh->status)->toBe(PurchaseRequestStatus::UnderReview);

        return $fresh;
    });
}

it('allows financial review while status is under_review', function (): void {
    $ctx = ivWp04Context();
    ivWp04PublishMatrix($ctx);
    ivWp04GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp04RoutedPr($ctx);
    expect($pr->status)->toBe(PurchaseRequestStatus::UnderReview);

    Sanctum::actingAs($ctx['reviewerOnly']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/financial-reviews', [
        'outcome' => 'cleared',
        'justification' => 'مراجعة مالية أثناء مسار الاعتماد تحت المراجعة',
        'version_no' => 1,
    ])->assertCreated()->assertJsonPath('data.outcome', 'cleared');

    $count = withTenant($ctx['tenant'], fn () => PurchaseRequestFinancialReview::query()
        ->where('purchase_request_id', $pr->id)
        ->where('version_no', 1)
        ->count());
    expect($count)->toBe(1);

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::UnderReview);
});

it('blocks final approve without a qualifying financial review', function (): void {
    $ctx = ivWp04Context();
    ivWp04PublishMatrix($ctx, levels: 1);
    ivWp04GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp04RoutedPr($ctx);

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PR_FINANCIAL_REVIEW_REQUIRED');

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(RecordPurchaseRequestFinancialReview::class)->execute(
            $ctx['reviewerOnly'],
            $pr->fresh() ?? $pr,
            [
                'outcome' => 'not_cleared',
                'justification' => 'المراجعة المالية غير مكتملة ولا تسمح بالاعتماد',
                'version_no' => 1,
            ],
            $ctx['request'],
        );
    });

    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PR_FINANCIAL_REVIEW_NOT_CLEARED');
});

it('blocks financial reviewer from completing final approve (SoD)', function (): void {
    $ctx = ivWp04Context();
    ivWp04PublishMatrix($ctx, levels: 2);
    ivWp04GrantAuthority($ctx, $ctx['approver']);
    ivWp04GrantAuthority($ctx, $ctx['frApprover']);

    $pr = ivWp04RoutedPr($ctx);

    // Level 1 by independent approver.
    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')->assertOk();

    // FR by the other authority holder.
    Sanctum::actingAs($ctx['frApprover']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/financial-reviews', [
        'outcome' => 'cleared',
        'justification' => 'مراجعة مالية من معتمد يملك صلاحية المراجعة أيضاً',
        'version_no' => 1,
    ])->assertCreated();

    // Same actor must not final-approve.
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PR_FINANCIAL_REVIEWER_SOD');

    // Pool for final excludes FR reviewer.
    $finalPool = withTenant($ctx['tenant'], function () use ($pr): array {
        return app(PurchaseRequestApproverPoolResolver::class)
            ->eligibleUserIds($pr->fresh() ?? $pr, forFinalLevel: true);
    });
    expect($finalPool)->toContain($ctx['approver']->id)
        ->and($finalPool)->not->toContain($ctx['frApprover']->id);

    // Independent approver can complete final.
    Sanctum::actingAs($ctx['approver']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('blocks creator from approving at any level', function (): void {
    $ctx = ivWp04Context();
    ivWp04PublishMatrix($ctx, levels: 1);
    // Creator has approve + authority — still excluded by SoD / pool.
    ivWp04GrantAuthority($ctx, $ctx['creator']);
    ivWp04GrantAuthority($ctx, $ctx['approver']);

    $pr = ivWp04RoutedPr($ctx);

    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        app(RecordPurchaseRequestFinancialReview::class)->execute(
            $ctx['reviewerOnly'],
            $pr->fresh() ?? $pr,
            [
                'outcome' => 'cleared',
                'justification' => 'مراجعة مالية مستقلة قبل محاولة اعتماد المنشئ',
                'version_no' => 1,
            ],
            $ctx['request'],
        );
    });

    Sanctum::actingAs($ctx['creator']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/approve')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PR_SOD_VIOLATION');
});

it('returns PR_NO_ELIGIBLE_FINAL_APPROVER when final pool is empty', function (): void {
    $ctx = ivWp04Context();
    ivWp04PublishMatrix($ctx, levels: 1);
    // Only FR+approve holder has authority → after FR exclusion final pool is empty.
    ivWp04GrantAuthority($ctx, $ctx['frApprover']);

    $pr = ivWp04RoutedPr($ctx);

    Sanctum::actingAs($ctx['frApprover']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/financial-reviews', [
        'outcome' => 'cleared_with_conditions',
        'justification' => 'مراجعة مالية تجعل المُراجع الوحيد غير مؤهل للنهائي',
        'version_no' => 1,
    ])->assertCreated();

    $finalPool = withTenant($ctx['tenant'], function () use ($pr): array {
        return app(PurchaseRequestApproverPoolResolver::class)
            ->eligibleUserIds($pr->fresh() ?? $pr, forFinalLevel: true);
    });
    expect($finalPool)->toBe([]);

    // Actor who is not the FR reviewer and not in pool still hits empty-final-pool via gate.
    // Use approver without authority — FinalApprovalGate empty check precedes in_array.
    $outsider = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $ctx['tenant']);

    withTenant($ctx['tenant'], function () use ($ctx, $pr, $outsider): void {
        try {
            app(ApprovePurchaseRequestStep::class)->execute(
                $outsider,
                $pr->fresh() ?? $pr,
                [],
                $ctx['request'],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('PR_NO_ELIGIBLE_FINAL_APPROVER');
        }
    });

    // Gate unit-level: empty pool always blocked.
    withTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        try {
            app(FinalApprovalGate::class)->assertCanComplete(
                $pr->fresh() ?? $pr,
                $ctx['approver'],
                [],
            );
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('PR_NO_ELIGIBLE_FINAL_APPROVER');
        }
    });
});
