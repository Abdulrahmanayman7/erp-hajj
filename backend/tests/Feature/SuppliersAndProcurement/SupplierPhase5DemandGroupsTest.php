<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\CancelApprovedPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase V — demand groups lifecycle, exclusive link, cancel unlink, engagement freeze.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase5DemandGroupsTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Phase V demand-group tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{tenant: Tenant, unit: OrganizationUnit, planner: User, viewer: User, canceller: User}
 */
function phase5DgContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $planner = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.consolidate_planning',
        'purchase_requests.cancel_approved',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'purchase_requests.view',
    ], $tenant);

    $canceller = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.cancel_approved',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'planner' => $planner,
        'viewer' => $viewer,
        'canceller' => $canceller,
    ];
}

function phase5ApprovedPr(array $ctx, string $suffix = ''): PurchaseRequest
{
    $suffix = $suffix !== '' ? $suffix : uniqid('p5_', true);

    return withTenant($ctx['tenant'], function () use ($ctx, $suffix): PurchaseRequest {
        return PurchaseRequest::factory()->create([
            'requested_by' => $ctx['planner']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'طلب معتمد '.$suffix,
            'purpose' => 'غرض اختبار مجموعات التخطيط '.$suffix,
            'estimated_total_priced' => '1500.00',
            'has_unpriced_lines' => false,
            'submitted_at' => now()->subDays(2),
        ]);
    });
}

it('creates transitions links and rejects non-approved and dual membership', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);

    $create = $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة تخطيط موسم',
        'notes' => 'ملاحظات',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.group_number', 'DG-000001');

    $groupId = (int) $create->json('data.id');

    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/transition', [
        'to' => 'active',
    ])->assertOk()->assertJsonPath('data.status', 'active');

    $pr = phase5ApprovedPr($ctx, 'link1');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated()
        ->assertJsonPath('data.active_member_count', 1);

    $draftPr = withTenant($ctx['tenant'], fn () => PurchaseRequest::factory()->create([
        'requested_by' => $ctx['planner']->id,
        'beneficiary_organization_unit_id' => $ctx['unit']->id,
        'status' => PurchaseRequestStatus::Draft,
        'pr_number' => 'PR-800001',
    ]));

    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $draftPr->id,
    ])->assertStatus(422)->assertJsonPath('code', 'DG_PR_NOT_APPROVED');

    $other = $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة ثانية',
    ])->assertCreated();
    $otherId = (int) $other->json('data.id');
    $this->postJson('/api/v1/purchase-demand-groups/'.$otherId.'/transition', ['to' => 'active'])->assertOk();

    $this->postJson('/api/v1/purchase-demand-groups/'.$otherId.'/items', [
        'purchase_request_id' => $pr->id,
    ])->assertStatus(409)->assertJsonPath('code', 'DG_PR_ALREADY_GROUPED');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DEMAND_GROUP_CREATED,
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DEMAND_GROUP_PR_LINKED,
    );
});

it('allows view with purchase_requests.view and forbids mutate without consolidate_planning', function (): void {
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);
    $id = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة للقراءة',
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($ctx['viewer']);
    $this->getJson('/api/v1/purchase-demand-groups/'.$id)->assertOk();
    $this->postJson('/api/v1/purchase-demand-groups', ['title' => 'ممنوع'])->assertForbidden();
    $this->patchJson('/api/v1/purchase-demand-groups/'.$id, ['title' => 'تعديل'])->assertForbidden();
});

it('locks membership when any active member is engaged and never writes engagement fields', function (): void {
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);

    $groupId = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة تجميد ارتباط',
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/transition', ['to' => 'active'])->assertOk();

    $pr = phase5ApprovedPr($ctx, 'eng1');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated();

    withTenant($ctx['tenant'], function () use ($pr): void {
        $pr->forceFill([
            'procurement_engaged_at' => now(),
            'procurement_engagement_ref' => 'S05-FIXTURE',
        ])->save();
    });

    $pr2 = phase5ApprovedPr($ctx, 'eng2');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $pr2->id,
    ])->assertStatus(422)->assertJsonPath('code', 'DG_MEMBERSHIP_LOCKED_ENGAGED');

    $this->deleteJson('/api/v1/purchase-demand-groups/'.$groupId.'/items/'.$pr->id)
        ->assertStatus(422)
        ->assertJsonPath('code', 'DG_MEMBERSHIP_LOCKED_ENGAGED');

    $freshPr = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($freshPr->procurement_engaged_at)->not->toBeNull()
        ->and($freshPr->procurement_engagement_ref)->toBe('S05-FIXTURE');

    $group = withTenant($ctx['tenant'], fn () => PurchaseDemandGroup::query()->whereKey($groupId)->firstOrFail());
    expect($group->status)->toBe(PurchaseDemandGroupStatus::Frozen)
        ->and($group->freeze_reason)->toBe('engagement');
});

it('auto-unlinks on cancel_approved in the same transaction without weakening engagement gate', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);

    $groupId = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة إلغاء',
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/transition', ['to' => 'active'])->assertOk();

    $pr = phase5ApprovedPr($ctx, 'cancel1');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated();

    expect(withTenant(
        $ctx['tenant'],
        fn () => PurchaseDemandGroupActiveMembership::query()->where('purchase_request_id', $pr->id)->exists(),
    ))->toBeTrue();

    Sanctum::actingAs($ctx['canceller']);
    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'إلغاء معتمد مع فك ربط مجموعة التخطيط تلقائياً',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    withTenant($ctx['tenant'], function () use ($pr, $groupId): void {
        expect(PurchaseDemandGroupActiveMembership::query()->where('purchase_request_id', $pr->id)->exists())->toBeFalse();

        $item = PurchaseDemandGroupItem::query()
            ->where('demand_group_id', $groupId)
            ->where('purchase_request_id', $pr->id)
            ->firstOrFail();
        expect($item->unlinked_at)->not->toBeNull()
            ->and($item->unlink_reason)->toBe('cancel_approved');
    });

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DEMAND_GROUP_PR_UNLINKED
            && ($e->context['unlink_reason'] ?? null) === 'cancel_approved',
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_CANCEL_APPROVED,
    );

    // Engagement gate still blocks cancel_approved.
    $pr2 = phase5ApprovedPr($ctx, 'cancel2');
    withTenant($ctx['tenant'], function () use ($pr2): void {
        $pr2->forceFill(['procurement_engaged_at' => now()])->save();
    });
    Sanctum::actingAs($ctx['canceller']);
    $this->postJson('/api/v1/purchase-requests/'.$pr2->id.'/cancel-approved', [
        'reason' => 'محاولة إلغاء بعد بدء الشراء اللاحق',
    ])->assertStatus(422)->assertJsonPath('code', 'PR_PROCUREMENT_ENGAGED');
});

it('returns 404 for cross-tenant demand group access', function (): void {
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);
    $id = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة محلية',
    ])->assertCreated()->json('data.id');

    $other = Tenant::factory()->create();
    provisionTenantRbac($other);
    $intruder = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.consolidate_planning',
    ], $other);

    Sanctum::actingAs($intruder);
    $this->getJson('/api/v1/purchase-demand-groups/'.$id)->assertNotFound();
    $this->postJson('/api/v1/purchase-demand-groups/'.$id.'/items', [
        'purchase_request_id' => 1,
    ])->assertNotFound();
});

it('forbids invalid lifecycle transitions from closed or frozen to draft', function (): void {
    $ctx = phase5DgContext();
    Sanctum::actingAs($ctx['planner']);

    $id = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'دورة حياة',
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/purchase-demand-groups/'.$id.'/transition', ['to' => 'closed'])->assertOk();
    $this->postJson('/api/v1/purchase-demand-groups/'.$id.'/transition', ['to' => 'active'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'DG_INVALID_TRANSITION');

    $id2 = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'تجميد يدوي',
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/purchase-demand-groups/'.$id2.'/transition', [
        'to' => 'frozen',
        'reason' => 'manual',
    ])->assertOk();
    $this->postJson('/api/v1/purchase-demand-groups/'.$id2.'/transition', ['to' => 'draft'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'DG_INVALID_TRANSITION');
});

it('cancel unlink is idempotent when PR was never grouped', function (): void {
    $ctx = phase5DgContext();
    $pr = phase5ApprovedPr($ctx, 'noid');

    $request = Request::create('/cancel-approved', 'POST');
    withTenant($ctx['tenant'], function () use ($ctx, $pr, $request): void {
        app(CancelApprovedPurchaseRequest::class)->execute(
            $ctx['canceller'],
            $pr,
            ['reason' => 'إلغاء معتمد بدون مجموعة تخطيط مرتبطة'],
            $request,
        );
    });

    $fresh = withTenant($ctx['tenant'], fn () => PurchaseRequest::query()->whereKey($pr->id)->firstOrFail());
    expect($fresh->status)->toBe(PurchaseRequestStatus::Cancelled);
});
