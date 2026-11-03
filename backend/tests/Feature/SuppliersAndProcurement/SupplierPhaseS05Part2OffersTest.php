<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 2 — invitations, offers, selection.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part2OffersTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, viewer: User}
 */
function s05p2Context(): array
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
        'rfq.invite',
        'rfq.record_offer',
        'rfq.select_supplier',
        'purchase_requests.view',
        'suppliers.view',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'rfq.view',
        'purchase_requests.view',
    ], $tenant);

    return compact('tenant', 'other', 'unit', 'actor', 'viewer');
}

function s05p2EligibleSupplier(Tenant $tenant, ?string $configKey = null): Supplier
{
    return withTenant($tenant, function () use ($tenant, $configKey): Supplier {
        $key = $configKey ?? ('s05p2-'.uniqid('', true));
        $set = approvedEligibilityConfig($tenant, [[
            'requirement_key' => 'commercial_registration',
            'is_mandatory' => true,
        ]], $key);
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        supplierDocumentRecordFor($tenant, $supplier, $def);

        return $supplier->fresh() ?? $supplier;
    });
}

/**
 * @return array{pr: PurchaseRequest, rfq: ProcurementRfq, line: ProcurementRfqLine}
 */
function s05p2PublishedRfq(array $ctx): array
{
    $pr = withTenant($ctx['tenant'], function () use ($ctx): PurchaseRequest {
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        $prLine = new PurchaseRequestLine([
            'description' => 'صنف',
            'quantity' => '3.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $prLine->forceFill([
            'purchase_request_id' => $pr->id,
            'line_no' => 1,
        ]);
        $prLine->save();

        return $pr->fresh() ?? $pr;
    });

    Sanctum::actingAs($ctx['actor']);
    $rfqId = (int) test()->postJson('/api/v1/rfqs', [
        'purchase_request_id' => $pr->id,
    ])->assertCreated()->json('data.id');

    test()->postJson('/api/v1/rfqs/'.$rfqId.'/publish')->assertOk();

    return withTenant($ctx['tenant'], function () use ($pr, $rfqId): array {
        $rfq = ProcurementRfq::query()->findOrFail($rfqId);
        $line = $rfq->lines()->firstOrFail();

        return compact('pr', 'rfq', 'line');
    });
}

it('invites eligible active supplier and rejects ineligible', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p2Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p2PublishedRfq($ctx);
    $eligible = s05p2EligibleSupplier($ctx['tenant'], 's05p2-invite');
    $ineligible = withTenant($ctx['tenant'], fn () => Supplier::factory()->create([
        'status' => SupplierStatus::Active,
    ]));

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', [
        'supplier_id' => $eligible->id,
    ])->assertCreated()
        ->assertJsonPath('data.supplier_id', $eligible->id)
        ->assertJsonPath('data.status', 'invited');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RFQ_SUPPLIER_INVITED);

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', [
        'supplier_id' => $ineligible->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE');

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', [
        'supplier_id' => $eligible->id,
    ])->assertStatus(422)->assertJsonPath('code', 'RFQ_SUPPLIER_ALREADY_INVITED');
});

it('records submits withdraws offers and selects supplier with rationale', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p2Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p2PublishedRfq($ctx);
    $configKey = 's05p2-select-'.uniqid('', true);
    $supplier = s05p2EligibleSupplier($ctx['tenant'], $configKey);
    $other = withTenant($ctx['tenant'], function () use ($ctx, $configKey): Supplier {
        $set = SupplierEligibilityRequirementSet::query()
            ->where('config_key', $configKey)
            ->orderByDesc('version_no')
            ->firstOrFail();
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        supplierDocumentRecordFor($ctx['tenant'], $supplier, $def);

        return $supplier->fresh() ?? $supplier;
    });

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', ['supplier_id' => $supplier->id])->assertCreated();
    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', ['supplier_id' => $other->id])->assertCreated();

    $offerId = (int) $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 12.5,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->json('data.id');

    $this->postJson('/api/v1/offers/'.$offerId.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::OFFER_SUBMITTED);

    $otherOfferId = (int) $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $other->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 20,
        ]],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/offers/'.$otherOfferId.'/submit')->assertOk();

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => '',
    ])->assertStatus(422)->assertJsonPath('code', 'SELECTION_RATIONALE_REQUIRED');

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => 'أفضل سعر وجودة مناسبة للحملة',
    ])->assertOk()
        ->assertJsonPath('data.status', 'awarded')
        ->assertJsonPath('data.selected_supplier_id', $supplier->id);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::RFQ_SUPPLIER_SELECTED);

    withTenant($ctx['tenant'], function () use ($offerId, $otherOfferId, $fx): void {
        expect(SupplierOffer::query()->findOrFail($offerId)->status)->toBe(SupplierOfferStatus::Selected);
        expect(SupplierOffer::query()->findOrFail($otherOfferId)->status)->toBe(SupplierOfferStatus::Rejected);
        expect(ProcurementRfq::query()->findOrFail($fx['rfq']->id)->status)->toBe(ProcurementRfqStatus::Awarded);
    });

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/select-supplier', [
        'supplier_id' => $other->id,
        'selection_rationale' => 'محاولة ترسية ثانية غير مسموحة',
    ])->assertStatus(422)->assertJsonPath('code', 'RFQ_ALREADY_AWARDED');
});

it('rejects award when supplier becomes ineligible and enforces authz isolation', function (): void {
    $ctx = s05p2Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p2PublishedRfq($ctx);
    $supplier = s05p2EligibleSupplier($ctx['tenant']);

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', ['supplier_id' => $supplier->id])->assertCreated();
    $offerId = (int) $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 10,
        ]],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/offers/'.$offerId.'/submit')->assertOk();

    withTenant($ctx['tenant'], function () use ($supplier): void {
        $supplier->forceFill(['status' => SupplierStatus::TemporarilySuspended])->save();
    });

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => 'ترسية بعد تعليق المورد يجب أن تفشل',
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_NOT_ACTIVE');

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', [
        'supplier_id' => $supplier->id,
    ])->assertForbidden();

    $foreign = createSupplierUserWith(['rfq.view', 'rfq.invite', 'rfq.record_offer', 'rfq.select_supplier'], $ctx['other']);
    Sanctum::actingAs($foreign);
    $this->getJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers')->assertNotFound();
    $this->postJson('/api/v1/offers/'.$offerId.'/withdraw')->assertNotFound();
});

it('enforces offer submit withdraw status rules and duplicate active offers', function (): void {
    $ctx = s05p2Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p2PublishedRfq($ctx);
    $supplier = s05p2EligibleSupplier($ctx['tenant']);
    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/invitations', ['supplier_id' => $supplier->id])->assertCreated();

    $offerId = (int) $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 8,
        ]],
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/offers/'.$offerId.'/withdraw')
        ->assertStatus(422)
        ->assertJsonPath('code', 'OFFER_INVALID_STATUS');

    $this->postJson('/api/v1/offers/'.$offerId.'/submit')->assertOk();
    $this->postJson('/api/v1/offers/'.$offerId.'/submit')
        ->assertStatus(422)
        ->assertJsonPath('code', 'OFFER_INVALID_STATUS');

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 9,
        ]],
    ])->assertStatus(422)->assertJsonPath('code', 'OFFER_ALREADY_EXISTS');

    $this->postJson('/api/v1/offers/'.$offerId.'/withdraw')->assertOk()->assertJsonPath('data.status', 'withdrawn');

    $this->postJson('/api/v1/rfqs/'.$fx['rfq']->id.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $fx['line']->id,
            'quantity' => 3,
            'unit_price' => 9.5,
        ]],
    ])->assertCreated();
});
