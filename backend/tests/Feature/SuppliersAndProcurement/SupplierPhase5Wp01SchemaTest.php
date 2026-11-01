<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase V WP01 — schema & exclusive membership uniqueness.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase5Wp01SchemaTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Phase V schema tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

it('exposes Phase V demand group tables and sequence', function (): void {
    expect(Schema::hasTable('purchase_demand_group_number_sequences'))->toBeTrue();
    expect(Schema::hasTable('purchase_demand_groups'))->toBeTrue();
    expect(Schema::hasTable('purchase_demand_group_items'))->toBeTrue();
    expect(Schema::hasTable('purchase_demand_group_active_memberships'))->toBeTrue();

    expect(Schema::hasColumn('purchase_demand_groups', 'group_number'))->toBeTrue();
    expect(Schema::hasColumn('purchase_demand_groups', 'freeze_reason'))->toBeTrue();
    expect(Schema::hasColumn('purchase_demand_groups', 'deleted_at'))->toBeTrue();
    expect(Schema::hasColumn('purchase_demand_group_items', 'pr_version_no_at_link'))->toBeTrue();
    expect(Schema::hasColumn('purchase_demand_group_active_memberships', 'item_id'))->toBeTrue();
});

it('allocates DG-###### numbers per tenant and scopes models', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $user = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));

    $n1 = withTenant($tenant, fn () => app(PurchaseDemandGroupNumberGenerator::class)->next());
    $n2 = withTenant($tenant, fn () => app(PurchaseDemandGroupNumberGenerator::class)->next());
    expect($n1)->toBe('DG-000001')
        ->and($n2)->toBe('DG-000002');

    $otherFirst = withTenant($other, fn () => app(PurchaseDemandGroupNumberGenerator::class)->next());
    expect($otherFirst)->toBe('DG-000001');

    $group = withTenant($tenant, function () use ($user): PurchaseDemandGroup {
        $g = new PurchaseDemandGroup(['title' => 'مجموعة اختبار', 'notes' => null]);
        $g->forceFill([
            'group_number' => 'DG-000099',
            'status' => PurchaseDemandGroupStatus::Draft,
            'created_by' => $user->id,
        ]);
        $g->save();

        return $g->fresh();
    });

    expect($group->status)->toBe(PurchaseDemandGroupStatus::Draft)
        ->and((int) $group->tenant_id)->toBe((int) $tenant->id);

    $visible = withTenant($other, fn () => PurchaseDemandGroup::query()->whereKey($group->id)->first());
    expect($visible)->toBeNull();
});

it('enforces UNIQUE tenant_id+purchase_request_id on active memberships', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $user = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    [$groupA, $groupB, $pr] = withTenant($tenant, function () use ($user, $unit): array {
        $g1 = new PurchaseDemandGroup(['title' => 'أ', 'notes' => null]);
        $g1->forceFill([
            'group_number' => 'DG-000010',
            'status' => PurchaseDemandGroupStatus::Active,
            'created_by' => $user->id,
        ]);
        $g1->save();

        $g2 = new PurchaseDemandGroup(['title' => 'ب', 'notes' => null]);
        $g2->forceFill([
            'group_number' => 'DG-000011',
            'status' => PurchaseDemandGroupStatus::Active,
            'created_by' => $user->id,
        ]);
        $g2->save();

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $user->id,
            'beneficiary_organization_unit_id' => $unit->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-900001',
        ]);

        return [$g1, $g2, $pr];
    });

    withTenant($tenant, function () use ($tenant, $groupA, $groupB, $pr, $user): void {
        $item = new PurchaseDemandGroupItem;
        $item->forceFill([
            'demand_group_id' => $groupA->id,
            'purchase_request_id' => $pr->id,
            'pr_version_no_at_link' => 1,
            'linked_at' => now(),
            'linked_by' => $user->id,
        ]);
        $item->save();

        DB::table('purchase_demand_group_active_memberships')->insert([
            'tenant_id' => $tenant->id,
            'purchase_request_id' => $pr->id,
            'demand_group_id' => $groupA->id,
            'item_id' => $item->id,
            'created_at' => now(),
        ]);

        $item2 = new PurchaseDemandGroupItem;
        $item2->forceFill([
            'demand_group_id' => $groupB->id,
            'purchase_request_id' => $pr->id,
            'pr_version_no_at_link' => 1,
            'linked_at' => now(),
            'linked_by' => $user->id,
        ]);
        $item2->save();

        expect(fn () => DB::table('purchase_demand_group_active_memberships')->insert([
            'tenant_id' => $tenant->id,
            'purchase_request_id' => $pr->id,
            'demand_group_id' => $groupB->id,
            'item_id' => $item2->id,
            'created_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(PurchaseDemandGroupActiveMembership::query()->where('purchase_request_id', $pr->id)->count())->toBe(1);
    });
});
