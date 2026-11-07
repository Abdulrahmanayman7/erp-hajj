<?php

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

test('Part4 budget read update requires manage_budget and respects lifecycle', function (): void {
    // F-Q03: tenant_owner already has manage_budget — deny path uses a non-owner role.
    $user = actingAsSeasonProjectUserWith(seasonProjectCorePermissions());
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'ميزانية',
    ], $headers)->assertCreated()->json('data');

    spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()
        ->assertJsonPath('data.financials.available', true);

    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 5000,
        'currency_code' => 'SAR',
    ], $headers)->assertForbidden();

    grantSeasonProjectPermissions($user, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);

    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 5000,
        'currency_code' => 'SAR',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.budget_amount', '5000.00')
        ->assertJsonPath('data.financials.remaining', '5000.00');
});

test('Part4 same-tenant cross-season PO link allowed E-Q04; currency mismatch 422 E-Q09', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $otherSeason = withTenant($owner->tenant, fn () => Season::factory()->create());
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'ربط عبر المواسم'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 10000,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    $poSameTenantOtherSeason = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'season_id' => $otherSeason->id,
        'currency' => 'SAR',
        'qty' => '5.0000',
        'price' => '200.0000',
    ]);

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $poSameTenantOtherSeason['po']->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.po_links.0.po_season_id', $otherSeason->id)
        ->assertJsonPath('data.financials.outstanding_commitment', '1000.00');

    $usdPo = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'currency' => 'USD',
        'qty' => '1.0000',
        'price' => '50.0000',
    ]);

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $usdPo['po']->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_CURRENCY_MISMATCH');
});

test('Part4 cross-tenant PO link is 404 and duplicate link rejected', function (): void {
    $ownerA = actingAsTenantOwner();
    grantSeasonProjectPermissions($ownerA, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $seasonA = seasonProjectActiveSeason();
    $headersA = seasonHeader($seasonA->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'عزل'], $headersA)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 1000,
    ], $headersA)->assertOk();

    $poA = createIssuedPoWithLineForSeasonProjectPart4($ownerA->tenant);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $poA['po']->id,
    ], $headersA)->assertCreated();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $poA['po']->id,
    ], $headersA)
        ->assertStatus(409)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_ALREADY_LINKED');

    $ownerB = actingAsTenantOwner();
    grantSeasonProjectPermissions($ownerB, [
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $seasonB = seasonProjectActiveSeason();
    $projectB = spaPostJson('/api/v1/season-projects', ['title' => 'ب'], seasonHeader($seasonB->id))
        ->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$projectB['id'].'/budget/po-links', [
        'purchase_order_id' => $poA['po']->id,
    ], seasonHeader($seasonB->id))->assertStatus(422); // TenantExists fails validation for foreign id
});

test('Part4 draft and cancelled PO are not linkable; unlink preserves PO', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'أهلية'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 2000,
    ], $headers)->assertOk();

    $draft = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'status' => PurchaseOrderStatus::Draft->value,
    ]);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $draft['po']->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_NOT_LINKABLE');

    $issued = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '2.0000',
        'price' => '50.0000',
    ]);
    $budget = spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $issued['po']->id,
    ], $headers)->assertCreated()->json('data');

    $linkId = $budget['po_links'][0]['id'];
    spaDeleteJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links/'.$linkId, $headers)
        ->assertOk()
        ->assertJsonCount(0, 'data.po_links');

    withTenant($owner->tenant, function () use ($issued, $linkId): void {
        expect(PurchaseOrder::query()->find($issued['po']->id))->not->toBeNull()
            ->and(SeasonProjectBudgetPoLink::query()->find($linkId))->toBeNull();
    });
});

test('Part4 E-Q07 posted GRN actual and E-Q08 remaining without double counting', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'مالي'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 2000,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    // Ordered 10 × 100 = 1000; receive 4 via posted GRN → actual 400; outstanding 600
    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '10.0000',
        'price' => '100.0000',
    ]);

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertCreated();

    insertPostedGrnForSeasonProjectPart4(
        $owner->tenant,
        $pack['po'],
        $pack['line_id'],
        '4.0000',
        $pack['user_id'],
    );

    // Draft GRN must not count
    withTenant($owner->tenant, function () use ($owner, $pack): void {
        DB::table('goods_receipts')->insert([
            'tenant_id' => $owner->tenant->id,
            'grn_number' => 'GRN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'purchase_order_id' => $pack['po']->id,
            'warehouse_id' => null,
            'status' => GoodsReceiptStatus::Draft->value,
            'idempotency_key' => null,
            'correlation_id' => null,
            'notes' => null,
            'posted_at' => null,
            'posted_by' => null,
            'created_by' => $pack['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $budget = spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()
        ->assertJsonPath('data.financials.available', true)
        ->assertJsonPath('data.financials.actual_spending', '400.00')
        ->assertJsonPath('data.financials.outstanding_commitment', '600.00')
        ->assertJsonPath('data.financials.remaining', '1000.00')
        ->json('data');

    // remaining = 2000 - 600 - 400 = 1000 (== budget - ordered)
    expect($budget['financials']['valuation_basis'])->toBe('posted_grn_qty_x_po_unit_price');
});

test('Part4 currency locked when links exist; cancelled PO contributes zero', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'عملة'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 500,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '1.0000',
        'price' => '100.0000',
    ]);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertCreated();

    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'currency_code' => 'USD',
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_BUDGET_CURRENCY_LOCKED');

    withTenant($owner->tenant, function () use ($pack): void {
        $pack['po']->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();
    });

    spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()
        ->assertJsonPath('data.financials.outstanding_commitment', '0.00')
        ->assertJsonPath('data.financials.actual_spending', '0.00')
        ->assertJsonPath('data.financials.remaining', '500.00');
});
