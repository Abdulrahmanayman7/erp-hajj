<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 3 — purchase orders + optional contract link.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part3PurchaseOrderTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, viewer: User}
 */
function s05p3Context(): array
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
        'rfq.invite',
        'rfq.record_offer',
        'rfq.select_supplier',
        'purchase_orders.view',
        'purchase_orders.create',
        'purchase_orders.update',
        'purchase_orders.issue',
        'purchase_orders.cancel',
        'purchase_requests.view',
        'suppliers.view',
        'contracts.view',
        'contracts.create',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'rfq.view',
        'purchase_orders.view',
        'purchase_requests.view',
    ], $tenant);

    return compact('tenant', 'other', 'unit', 'actor', 'viewer');
}

function s05p3EligibleSupplier(Tenant $tenant, ?string $configKey = null): Supplier
{
    return withTenant($tenant, function () use ($tenant, $configKey): Supplier {
        $key = $configKey ?? ('s05p3-'.uniqid('', true));
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
 * @return array{pr: PurchaseRequest, rfq: ProcurementRfq, line: ProcurementRfqLine, supplier: Supplier, offer: SupplierOffer}
 */
function s05p3AwardedRfq(array $ctx, ?string $configKey = null): array
{
    $key = $configKey ?? ('s05p3-award-'.uniqid('', true));
    $supplier = s05p3EligibleSupplier($ctx['tenant'], $key);

    $pr = withTenant($ctx['tenant'], function () use ($ctx): PurchaseRequest {
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        $prLine = new PurchaseRequestLine([
            'description' => 'صنف PO',
            'quantity' => '5.0000',
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
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/invitations', ['supplier_id' => $supplier->id])->assertCreated();

    $line = withTenant($ctx['tenant'], fn () => ProcurementRfq::query()->findOrFail($rfqId)->lines()->firstOrFail());

    $offerId = (int) test()->postJson('/api/v1/rfqs/'.$rfqId.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => [[
            'procurement_rfq_line_id' => $line->id,
            'quantity' => 5,
            'unit_price' => 17.25,
        ]],
    ])->assertCreated()->json('data.id');

    test()->postJson('/api/v1/offers/'.$offerId.'/submit')->assertOk();
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => 'أفضل سعر وجودة مناسبة للترسية',
    ])->assertOk()->assertJsonPath('data.status', 'awarded');

    return withTenant($ctx['tenant'], function () use ($pr, $rfqId, $supplier, $offerId): array {
        $rfq = ProcurementRfq::query()->findOrFail($rfqId);
        $line = $rfq->lines()->firstOrFail();
        $offer = SupplierOffer::query()->findOrFail($offerId);

        return compact('pr', 'rfq', 'line', 'supplier', 'offer');
    });
}

it('creates PO from awarded RFQ with winning offer lines and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx);

    $response = $this->postJson('/api/v1/purchase-orders', [
        'rfq_id' => $fx['rfq']->id,
        'notes' => 'أمر شراء تجريبي',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.supplier_id', $fx['supplier']->id)
        ->assertJsonPath('data.procurement_rfq_id', $fx['rfq']->id)
        ->assertJsonPath('data.purchase_request_id', $fx['pr']->id)
        ->assertJsonPath('data.supplier_offer_id', $fx['offer']->id)
        ->assertJsonPath('data.lines.0.unit_price', '17.2500')
        ->assertJsonPath('data.lines.0.quantity_ordered', '5.0000');

    expect($response->json('data.po_number'))->toStartWith('PO-');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PO_CREATED,
    );
});

it('rejects PO from non-awarded RFQ', function (): void {
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);

    $pr = withTenant($ctx['tenant'], function () use ($ctx): PurchaseRequest {
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        $prLine = new PurchaseRequestLine([
            'description' => 'صنف',
            'quantity' => '1.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $prLine->forceFill(['purchase_request_id' => $pr->id, 'line_no' => 1]);
        $prLine->save();

        return $pr;
    });

    $rfqId = (int) $this->postJson('/api/v1/rfqs', ['purchase_request_id' => $pr->id])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/rfqs/'.$rfqId.'/publish')->assertOk();

    $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $rfqId])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PO_RFQ_NOT_AWARDED');
});

it('rejects duplicate PO from the same award', function (): void {
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx);

    $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])->assertCreated();
    $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PO_ALREADY_EXISTS');
});

it('issues and cancels PO with permission gates and receipt block', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx);

    $poId = (int) $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])
        ->assertCreated()->json('data.id');

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/purchase-orders/'.$poId.'/issue')->assertForbidden();

    Sanctum::actingAs($ctx['actor']);
    $this->postJson('/api/v1/purchase-orders/'.$poId.'/issue')
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PO_ISSUED,
    );

    withTenant($ctx['tenant'], function () use ($poId): void {
        DB::table('purchase_order_lines')
            ->where('purchase_order_id', $poId)
            ->update(['quantity_received' => 1]);
    });

    $this->postJson('/api/v1/purchase-orders/'.$poId.'/cancel', [
        'reason' => 'محاولة إلغاء بعد استلام جزئي',
    ])->assertStatus(422)->assertJsonPath('code', 'PO_HAS_RECEIPTS');

    withTenant($ctx['tenant'], function () use ($poId): void {
        DB::table('purchase_order_lines')
            ->where('purchase_order_id', $poId)
            ->update(['quantity_received' => 0]);
    });

    $this->postJson('/api/v1/purchase-orders/'.$poId.'/cancel', [
        'reason' => 'إلغاء قبل أي استلام فعلي',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PO_CANCELLED,
    );
});

it('links optional same-tenant contract and rejects supplier mismatch / cross-tenant', function (): void {
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx, 's05p3-contract');

    $okContract = withTenant($ctx['tenant'], fn () => Contract::factory()->create([
        'created_by' => $ctx['actor']->id,
        'supplier_id' => null,
    ]));

    $mismatchContract = withTenant($ctx['tenant'], function () use ($ctx, $fx): Contract {
        $otherSupplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);

        return Contract::factory()->create([
            'created_by' => $ctx['actor']->id,
            'supplier_id' => $otherSupplier->id,
        ]);
    });

    $foreignContractId = withTenant($ctx['other'], function () use ($ctx): int {
        $foreignActor = createSupplierUserWith(['contracts.create'], $ctx['other']);

        return (int) Contract::factory()->create([
            'created_by' => $foreignActor->id,
        ])->id;
    });

    $poId = (int) $this->postJson('/api/v1/purchase-orders', [
        'rfq_id' => $fx['rfq']->id,
        'contract_id' => $okContract->id,
    ])->assertCreated()
        ->assertJsonPath('data.contract_id', $okContract->id)
        ->json('data.id');

    $this->patchJson('/api/v1/purchase-orders/'.$poId, [
        'contract_id' => $mismatchContract->id,
    ])->assertStatus(422)->assertJsonPath('code', 'CONTRACT_SUPPLIER_MISMATCH');

    // Cross-tenant contract id is invisible under tenant scope → 404.
    $this->patchJson('/api/v1/purchase-orders/'.$poId, [
        'contract_id' => $foreignContractId,
    ])->assertNotFound();
});

it('returns 404 for cross-tenant PO access and enforces RBAC on create', function (): void {
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx);

    $poId = (int) $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])
        ->assertCreated()->json('data.id');

    $otherUser = createSupplierUserWith([
        'purchase_orders.view',
        'purchase_orders.create',
    ], $ctx['other']);

    Sanctum::actingAs($otherUser);
    $this->getJson('/api/v1/purchase-orders/'.$poId)->assertNotFound();

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])->assertForbidden();
});

it('blocks updating issued PO and keeps supplier/lines immutable via draft update only', function (): void {
    $ctx = s05p3Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p3AwardedRfq($ctx);

    $poId = (int) $this->postJson('/api/v1/purchase-orders', ['rfq_id' => $fx['rfq']->id])
        ->assertCreated()->json('data.id');

    $this->patchJson('/api/v1/purchase-orders/'.$poId, [
        'notes' => 'ملاحظات محدثة',
    ])->assertOk()->assertJsonPath('data.notes', 'ملاحظات محدثة');

    $this->postJson('/api/v1/purchase-orders/'.$poId.'/issue')->assertOk();

    $this->patchJson('/api/v1/purchase-orders/'.$poId, [
        'notes' => 'محاولة بعد الإصدار',
    ])->assertStatus(422)->assertJsonPath('code', 'PO_INVALID_STATUS');

    withTenant($ctx['tenant'], function () use ($poId, $fx): void {
        $po = PurchaseOrder::query()->findOrFail($poId);
        expect($po->status)->toBe(PurchaseOrderStatus::Issued)
            ->and((int) $po->supplier_id)->toBe((int) $fx['supplier']->id)
            ->and($po->lines)->toHaveCount(1)
            ->and((string) $po->lines->first()->unit_price)->toBe('17.2500');
    });
});
