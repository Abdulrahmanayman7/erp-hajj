<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\SubmitPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP05 — depth-1 delegation + SLA notify-only.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp05DelegationSlaTest.php
 */

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP05 delegation/SLA tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: User,
 *   delegator: User,
 *   delegate: User,
 *   other: User,
 *   admin: User,
 *   delegationAdmin: User,
 *   request: Request
 * }
 */
function ivWp05Context(): array
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

    $delegator = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $tenant);

    $delegate = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $tenant);

    $other = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.approve',
    ], $tenant);

    $admin = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.manage_approval_config',
    ], $tenant);

    $delegationAdmin = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.manage_delegations',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'creator' => $creator,
        'delegator' => $delegator,
        'delegate' => $delegate,
        'other' => $other,
        'admin' => $admin,
        'delegationAdmin' => $delegationAdmin,
        'request' => Request::create('/purchase-approval-delegations', 'POST'),
    ];
}

function ivWp05PublishMatrix(array $ctx): PurchaseApprovalMatrix
{
    return withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalMatrix {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => 'مصفوفة تفويض SLA'],
            $ctx['request'],
        );

        app(UpsertPurchaseApprovalMatrixLevel::class)->execute(
            $ctx['admin'],
            $matrix,
            ['level_order' => 1, 'name' => 'اعتماد أول'],
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

function ivWp05GrantAuthority(array $ctx, User $subject, string $maxAmount = '50000.00', bool $coversUnpriced = true): void
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

function ivWp05RoutedPr(array $ctx, string $unitEstimate = '100'): PurchaseRequest
{
    $suffix = uniqid('wp05_', true);
    $payload = [
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب تفويض SLA '.$suffix,
        'purpose' => 'اختبار تفويض واعتماد SLA '.$suffix,
        'priority' => 'urgent',
        'urgency_justification' => 'مبرر استعجال كافٍ لاختبار التفويض وSLA',
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [[
            'line_no' => 1,
            'description' => 'مستلزم '.$suffix,
            'quantity' => 10,
            'unit' => 'قطعة',
            'need_date' => now()->addDays(14)->toDateString(),
            'unit_estimate' => (float) $unitEstimate,
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

        return PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
    });
}

it('creates self-delegation when actor has approve + direct authority', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '20000.00');

    Sanctum::actingAs($ctx['delegator']);
    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['delegate']->id,
        'max_amount' => '15000.00',
        'starts_at' => now()->subMinute()->toIso8601String(),
        'ends_at' => now()->addDays(3)->toIso8601String(),
    ])->assertCreated()
        ->assertJsonPath('data.from_user_id', $ctx['delegator']->id)
        ->assertJsonPath('data.to_user_id', $ctx['delegate']->id)
        ->assertJsonPath('data.max_amount', '15000.00');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DELEGATION_CREATED,
    );
});

it('clamps delegation max_amount to delegator direct ceiling', function (): void {
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '10000.00');

    Sanctum::actingAs($ctx['delegator']);
    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['delegate']->id,
        'max_amount' => '50000.00',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated()
        ->assertJsonPath('data.max_amount', '10000.00');
});

it('rejects from=to and re-delegation without direct authority', function (): void {
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '20000.00');

    // First hop: delegator → delegate
    withTenant($ctx['tenant'], function () use ($ctx): void {
        app(CreatePurchaseApprovalDelegation::class)->execute(
            $ctx['delegator'],
            [
                'to_user_id' => $ctx['delegate']->id,
                'max_amount' => '10000',
                'starts_at' => now()->subMinute()->toIso8601String(),
                'ends_at' => now()->addDays(2)->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    Sanctum::actingAs($ctx['delegator']);
    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['delegator']->id,
        'max_amount' => '5000',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422)->assertJsonPath('code', 'PR_DELEGATION_INVALID');

    // Delegate has no direct authority — cannot create outbound
    Sanctum::actingAs($ctx['delegate']);
    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['other']->id,
        'max_amount' => '5000',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422)->assertJsonPath('code', 'PR_DELEGATION_INVALID');
});

it('rejects overlapping active from-to windows', function (): void {
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '20000.00');

    Sanctum::actingAs($ctx['delegator']);
    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['delegate']->id,
        'max_amount' => '8000',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
    ])->assertCreated();

    $this->postJson('/api/v1/purchase-approval-delegations', [
        'to_user_id' => $ctx['delegate']->id,
        'max_amount' => '7000',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(6)->toIso8601String(),
    ])->assertStatus(422)->assertJsonPath('code', 'PR_DELEGATION_INVALID');
});

it('admin manage_delegations can create and revoke for others', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '25000.00');

    Sanctum::actingAs($ctx['delegationAdmin']);
    $res = $this->postJson('/api/v1/purchase-approval-delegations', [
        'from_user_id' => $ctx['delegator']->id,
        'to_user_id' => $ctx['delegate']->id,
        'max_amount' => '12000',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ])->assertCreated();

    $id = (int) $res->json('data.id');

    $this->postJson('/api/v1/purchase-approval-delegations/'.$id.'/revoke')
        ->assertOk()
        ->assertJsonPath('data.revoked_at', fn ($v) => $v !== null);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DELEGATION_REVOKED,
    );
});

it('includes depth-1 delegate in pool with capped ceiling and SoD on acting user', function (): void {
    $ctx = ivWp05Context();
    ivWp05PublishMatrix($ctx);
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '50000.00');

    withTenant($ctx['tenant'], function () use ($ctx): void {
        app(CreatePurchaseApprovalDelegation::class)->execute(
            $ctx['delegator'],
            [
                'to_user_id' => $ctx['delegate']->id,
                'max_amount' => '5000.00',
                'starts_at' => now()->subMinute()->toIso8601String(),
                'ends_at' => now()->addDays(3)->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    // PR total 1000 — within delegation cap
    $prLow = ivWp05RoutedPr($ctx, '100');
    expect($prLow->status)->toBe(PurchaseRequestStatus::UnderReview);

    $poolLow = withTenant($ctx['tenant'], fn () => app(PurchaseRequestApproverPoolResolver::class)
        ->eligibleUserIds($prLow));

    expect($poolLow)->toContain($ctx['delegator']->id)
        ->and($poolLow)->toContain($ctx['delegate']->id)
        ->and($poolLow)->not->toContain($ctx['creator']->id);

    // PR total 20000 — above delegation cap 5000; delegate out, delegator in
    $prHigh = ivWp05RoutedPr($ctx, '2000');
    $poolHigh = withTenant($ctx['tenant'], fn () => app(PurchaseRequestApproverPoolResolver::class)
        ->eligibleUserIds($prHigh));

    expect($poolHigh)->toContain($ctx['delegator']->id)
        ->and($poolHigh)->not->toContain($ctx['delegate']->id);
});

it('revoked or expired delegation drops from pool', function (): void {
    $ctx = ivWp05Context();
    ivWp05PublishMatrix($ctx);
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '50000.00');

    $delegation = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalDelegation {
        return app(CreatePurchaseApprovalDelegation::class)->execute(
            $ctx['delegator'],
            [
                'to_user_id' => $ctx['delegate']->id,
                'max_amount' => '20000',
                'starts_at' => now()->subMinute()->toIso8601String(),
                'ends_at' => now()->addDays(2)->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    $pr = ivWp05RoutedPr($ctx);
    $pool = withTenant($ctx['tenant'], fn () => app(PurchaseRequestApproverPoolResolver::class)->eligibleUserIds($pr));
    expect($pool)->toContain($ctx['delegate']->id);

    Sanctum::actingAs($ctx['delegator']);
    $this->postJson('/api/v1/purchase-approval-delegations/'.$delegation->id.'/revoke')->assertOk();

    $poolAfter = withTenant($ctx['tenant'], fn () => app(PurchaseRequestApproverPoolResolver::class)->eligibleUserIds($pr));
    expect($poolAfter)->not->toContain($ctx['delegate']->id);
});

it('upserts and deletes SLA policies with manage_approval_config', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp05Context();

    Sanctum::actingAs($ctx['admin']);
    $this->putJson('/api/v1/purchase-approval-sla-policies', [
        'priority' => 'urgent',
        'level_order' => 1,
        'target_hours' => 24,
        'reminder_offset_hours' => 4,
    ])->assertOk()
        ->assertJsonPath('data.target_hours', 24);

    $this->putJson('/api/v1/purchase-approval-sla-policies', [
        'priority' => 'urgent',
        'level_order' => 1,
        'target_hours' => 12,
    ])->assertOk()
        ->assertJsonPath('data.target_hours', 12);

    $id = (int) withTenant($ctx['tenant'], fn () => PurchaseApprovalSlaPolicy::query()->value('id'));

    $this->deleteJson('/api/v1/purchase-approval-sla-policies/'.$id)->assertOk();

    expect(withTenant($ctx['tenant'], fn () => PurchaseApprovalSlaPolicy::query()->count()))->toBe(0);

    Sanctum::actingAs($ctx['delegator']);
    $this->putJson('/api/v1/purchase-approval-sla-policies', [
        'priority' => 'normal',
        'level_order' => 1,
        'target_hours' => 8,
    ])->assertForbidden();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SLA_POLICY_UPSERTED,
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SLA_POLICY_DELETED,
    );
});

it('sets due_at from SLA on route and scan escalates without auto-approve', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = ivWp05Context();
    ivWp05PublishMatrix($ctx);
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '50000.00');

    withTenant($ctx['tenant'], function () use ($ctx): void {
        app(UpsertPurchaseApprovalSlaPolicy::class)->execute(
            $ctx['admin'],
            [
                'priority' => 'urgent',
                'level_order' => 1,
                'target_hours' => 1,
            ],
            $ctx['request'],
        );
    });

    $pr = ivWp05RoutedPr($ctx);
    expect($pr->status)->toBe(PurchaseRequestStatus::UnderReview);

    $step = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalStep::query()
        ->whereHas('instance', fn ($q) => $q->where('purchase_request_id', $pr->id))
        ->where('level_order', 1)
        ->firstOrFail());

    expect($step->due_at)->not->toBeNull()
        ->and($step->escalated_at)->toBeNull()
        ->and($step->status)->toBe(PurchaseRequestApprovalStepStatus::Pending);

    withTenant($ctx['tenant'], function () use ($step): void {
        $step->forceFill(['due_at' => now()->subHour()])->save();
    });

    Artisan::call('purchase-requests:scan-approval-sla');

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequestApprovalStep::query()->whereKey($step->id)->firstOrFail());
    expect($fresh->escalated_at)->not->toBeNull()
        ->and($fresh->status)->toBe(PurchaseRequestApprovalStepStatus::Pending);

    $prFresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($prFresh->status)->toBe(PurchaseRequestStatus::UnderReview);

    $notifCount = withTenant($ctx['tenant'], fn () => Notification::query()
        ->where('type', NotificationType::PurchaseApprovalSlaEscalated)
        ->where('entity_id', $pr->id)
        ->count());
    expect($notifCount)->toBeGreaterThan(0);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_SLA_ESCALATED,
    );
});

it('returns 404 for cross-tenant delegation access', function (): void {
    $ctx = ivWp05Context();
    ivWp05GrantAuthority($ctx, $ctx['delegator'], '10000.00');

    $delegation = withTenant($ctx['tenant'], function () use ($ctx): PurchaseApprovalDelegation {
        return app(CreatePurchaseApprovalDelegation::class)->execute(
            $ctx['delegator'],
            [
                'to_user_id' => $ctx['delegate']->id,
                'max_amount' => '5000',
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addDay()->toIso8601String(),
            ],
            $ctx['request'],
        );
    });

    $otherTenant = Tenant::factory()->create();
    provisionTenantRbac($otherTenant);
    $intruder = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.manage_delegations',
    ], $otherTenant);

    Sanctum::actingAs($intruder);
    $this->getJson('/api/v1/purchase-approval-delegations/'.$delegation->id)->assertNotFound();
    $this->postJson('/api/v1/purchase-approval-delegations/'.$delegation->id.'/revoke')->assertNotFound();
});
