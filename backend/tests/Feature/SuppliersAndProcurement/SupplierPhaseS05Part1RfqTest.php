<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 1 — RFQ foundation + engagement lock.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part1RfqTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, viewer: User}
 */
function s05p1Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    provisionTenantRbac($other);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $actor = createSupplierUserWith([
        'rfq.view',
        'rfq.create',
        'rfq.update',
        'rfq.publish',
        'rfq.cancel',
        'purchase_requests.view',
        'purchase_requests.cancel_approved',
        'purchase_requests.consolidate_planning',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'rfq.view',
        'purchase_requests.view',
    ], $tenant);

    return compact('tenant', 'other', 'unit', 'actor', 'viewer');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function s05p1ApprovedPr(array $ctx, string $suffix = '', array $overrides = []): PurchaseRequest
{
    $suffix = $suffix !== '' ? $suffix : uniqid('s05_', true);

    return withTenant($ctx['tenant'], function () use ($ctx, $suffix, $overrides): PurchaseRequest {
        $pr = PurchaseRequest::factory()->create(array_merge([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'request_kind' => PurchaseRequestKind::Administrative,
            'season_id' => null,
            'pr_number' => 'PR-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'طلب معتمد '.$suffix,
            'purpose' => 'غرض اختبار S05 '.$suffix,
            'submitted_at' => now()->subDay(),
        ], $overrides));

        $line = new PurchaseRequestLine([
            'description' => 'صنف اختبار',
            'specifications' => 'مواصفات',
            'quantity' => '10.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $line->forceFill([
            'purchase_request_id' => $pr->id,
            'line_no' => 1,
        ]);
        $line->save();

        return $pr->fresh(['lines']) ?? $pr;
    });
}

it('creates rfq from approved pr and rejects non-approved', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);

    $approved = s05p1ApprovedPr($ctx);
    $draft = withTenant($ctx['tenant'], function () use ($ctx): PurchaseRequest {
        return PurchaseRequest::factory()->create([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);
    });

    $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $approved->id,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.rfq_number', 'RFQ-000001')
        ->assertJsonPath('data.purchase_request_id', $approved->id);

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::RFQ_CREATED;
    });

    $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $draft->id,
    ])->assertStatus(422)
        ->assertJsonPath('code', 'RFQ_PR_NOT_APPROVED');
});

it('enforces authz and cross-tenant 404 for rfqs', function (): void {
    $ctx = s05p1Context();
    $approved = s05p1ApprovedPr($ctx);

    $this->postJson('/api/v1/rfqs', ['purchase_request_id' => $approved->id])
        ->assertUnauthorized();

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/rfqs', ['purchase_request_id' => $approved->id])
        ->assertForbidden();

    Sanctum::actingAs($ctx['actor']);
    $created = $this->postJson('/api/v1/rfqs', ['purchase_request_id' => $approved->id])
        ->assertCreated();
    $rfqId = (int) $created->json('data.id');

    $foreign = createSupplierUserWith(['rfq.view', 'rfq.create'], $ctx['other']);
    Sanctum::actingAs($foreign);
    $this->getJson('/api/v1/rfqs/'.$rfqId)->assertNotFound();
});

it('publishes rfq atomically writing engagement and blocks cancel-approved and dg membership', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);

    $pr = s05p1ApprovedPr($ctx, 'engage');

    $groupId = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة قبل الارتباط',
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/transition', ['to' => 'active'])
        ->assertOk();

    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated();

    $rfqId = (int) $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    withTenant($ctx['tenant'], function () use ($pr, $groupId): void {
        $pr->refresh();
        expect($pr->procurement_engaged_at)->not->toBeNull();
        expect($pr->procurement_engagement_ref)->toBe('RFQ-000001');

        $group = PurchaseDemandGroup::query()->findOrFail($groupId);
        expect($group->status)->toBe(PurchaseDemandGroupStatus::Frozen);
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::RFQ_PUBLISHED;
    });
    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::PR_PROCUREMENT_ENGAGED;
    });

    $this->postJson('/api/v1/purchase-requests/'.$pr->id.'/cancel-approved', [
        'reason' => 'محاولة إلغاء بعد بدء المشتريات',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PR_PROCUREMENT_ENGAGED');

    $otherPr = s05p1ApprovedPr($ctx, 'other');
    $this->postJson('/api/v1/purchase-demand-groups/'.$groupId.'/items', [
        'purchase_request_id' => $otherPr->id,
    ])->assertStatus(422)
        ->assertJsonPath('code', 'DG_MEMBERSHIP_LOCKED_ENGAGED');

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')
        ->assertStatus(422)
        ->assertJsonPath('code', 'RFQ_ALREADY_PUBLISHED');
});

it('rejects publish without lines and clears engagement on pre-award cancel', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);

    $pr = withTenant($ctx['tenant'], function () use ($ctx): PurchaseRequest {
        return PurchaseRequest::factory()->create([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'بدون أسطر',
            'purpose' => 'اختبار نشر بدون أسطر',
            'submitted_at' => now()->subDay(),
        ]);
    });

    $rfqId = (int) $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated()->json('data.id');

    // PR has no lines → RFQ created empty (publish must fail until lines added).
    withTenant($ctx['tenant'], function () use ($rfqId): void {
        expect(ProcurementRfq::query()->findOrFail($rfqId)->lines()->count())->toBe(0);
    });

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')
        ->assertStatus(422)
        ->assertJsonPath('code', 'RFQ_LINES_REQUIRED');

    $this->putJson('/api/v1/rfqs/'.$rfqId.'/lines', [
        'lines' => [[
            'description' => 'سطر واحد',
            'quantity' => 2,
            'unit' => 'علبة',
            'need_date' => now()->addDays(3)->toDateString(),
        ]],
    ])->assertOk();

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')->assertOk();
    withTenant($ctx['tenant'], function () use ($pr): void {
        $pr->refresh();
        expect($pr->procurement_engaged_at)->not->toBeNull();
    });

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/cancel', [
        'reason' => 'إلغاء قبل الترسية للاختبار',
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    withTenant($ctx['tenant'], function () use ($pr): void {
        $pr->refresh();
        expect($pr->procurement_engaged_at)->toBeNull();
        expect($pr->procurement_engagement_ref)->toBeNull();
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::PR_PROCUREMENT_ENGAGEMENT_CLEARED;
    });
});

it('exposes engagement fields on purchase request resource after publish', function (): void {
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);
    $pr = s05p1ApprovedPr($ctx, 'resource');

    $rfqId = (int) $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated()->json('data.id');

    $this->getJson('/api/v1/purchase-requests/'.$pr->id)
        ->assertOk()
        ->assertJsonPath('data.procurement_engaged_at', null);

    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')->assertOk();

    $this->getJson('/api/v1/purchase-requests/'.$pr->id)
        ->assertOk()
        ->assertJsonPath('data.procurement_engagement_ref', 'RFQ-000001');
});

it('does not engage pr when rfq remains draft', function (): void {
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);
    $pr = s05p1ApprovedPr($ctx, 'draft-only');

    $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $pr->id,
        'title' => 'مسودة فقط',
    ])->assertCreated();

    withTenant($ctx['tenant'], function () use ($pr): void {
        $pr->refresh();
        expect($pr->procurement_engaged_at)->toBeNull();
        expect(ProcurementRfq::query()->where('status', ProcurementRfqStatus::Draft)->count())->toBe(1);
        expect(PurchaseDemandGroupActiveMembership::query()->count())->toBe(0);
    });
});

it('AC-S05-R02 forces season_id from seasonal PR and omits season for administrative', function (): void {
    $ctx = s05p1Context();
    Sanctum::actingAs($ctx['actor']);

    $season = withTenant($ctx['tenant'], fn () => Season::factory()->active()->create());
    $foreignSeason = withTenant($ctx['other'], fn () => Season::factory()->active()->create());

    $adminPr = s05p1ApprovedPr($ctx, 'admin-kind', [
        'request_kind' => PurchaseRequestKind::Administrative,
        'season_id' => null,
    ]);
    $seasonalPr = s05p1ApprovedPr($ctx, 'seasonal-kind', [
        'request_kind' => PurchaseRequestKind::Seasonal,
        'season_id' => $season->id,
    ]);

    $adminRfq = $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $adminPr->id,
        'title' => 'RFQ إداري',
    ])->assertCreated()
        ->assertJsonPath('data.season_id', null)
        ->assertJsonPath('data.purchase_request_id', $adminPr->id)
        ->json('data');

    $seasonalRfq = $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $seasonalPr->id,
        'title' => 'RFQ موسمي',
    ])->assertCreated()
        ->assertJsonPath('data.season_id', $season->id)
        ->assertJsonPath('data.purchase_request_id', $seasonalPr->id)
        ->json('data');

    withTenant($ctx['tenant'], function () use ($adminRfq, $seasonalRfq, $season, $foreignSeason): void {
        $admin = ProcurementRfq::query()->findOrFail((int) $adminRfq['id']);
        $seasonal = ProcurementRfq::query()->findOrFail((int) $seasonalRfq['id']);

        expect($admin->season_id)->toBeNull();
        expect($seasonal->season_id)->toBe($season->id)
            ->and($seasonal->season_id)->not->toBe($foreignSeason->id)
            ->and($admin->tenant_id)->toBe($seasonal->tenant_id);
    });

    // Seasonal PR without season_id is rejected at create (tenant/season consistency).
    $brokenSeasonal = s05p1ApprovedPr($ctx, 'seasonal-broken', [
        'request_kind' => PurchaseRequestKind::Seasonal,
        'season_id' => null,
    ]);
    $this->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $brokenSeasonal->id,
    ])->assertStatus(422)->assertJsonPath('code', 'RFQ_SEASON_MISMATCH');
});
