<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase IV WP01-A — schema & models only.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01aApprovalConfigSchemaTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP01-A schema tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

it('exposes the four WP01-A approval config tables', function (): void {
    expect(Schema::hasTable('purchase_approval_matrices'))->toBeTrue();
    expect(Schema::hasTable('purchase_approval_matrix_rules'))->toBeTrue();
    expect(Schema::hasTable('purchase_approval_matrix_levels'))->toBeTrue();
    expect(Schema::hasTable('purchase_approver_authorities'))->toBeTrue();

    expect(Schema::hasColumn('purchase_approval_matrix_rules', 'applies_when_unpriced'))->toBeTrue();
    expect(Schema::hasColumn('purchase_approver_authorities', 'covers_unpriced'))->toBeTrue();
    expect(Schema::hasColumn('purchase_approver_authorities', 'max_amount'))->toBeTrue();
});

it('persists matrix rules levels and authority with tenant scope and relationships', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);

    $user = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $matrix = withTenant($tenant, function () use ($user): PurchaseApprovalMatrix {
        $m = new PurchaseApprovalMatrix(['name' => 'مسودة اعتماد']);
        $m->forceFill([
            'tenant_id' => $user->tenant_id,
            'status' => PurchaseApprovalMatrixStatus::Draft,
            'version_no' => 0,
            'created_by' => $user->id,
        ]);
        $m->save();

        return $m->fresh();
    });

    expect($matrix->status)->toBe(PurchaseApprovalMatrixStatus::Draft);
    expect($matrix->isDraft())->toBeTrue();

    withTenant($tenant, function () use ($matrix, $unit, $user): void {
        $rule = new PurchaseApprovalMatrixRule([
            'min_amount' => '1000.00',
            'max_amount' => '50000.00',
            'applies_when_unpriced' => true,
        ]);
        $rule->forceFill([
            'matrix_id' => $matrix->id,
            'organization_unit_id' => $unit->id,
            'request_kind' => null,
        ]);
        $rule->save();
        $rule = $rule->fresh();

        expect($rule->applies_when_unpriced)->toBeTrue();
        expect($rule->min_amount)->toBe('1000.00');
        expect($rule->matrix->is($matrix))->toBeTrue();

        $level = new PurchaseApprovalMatrixLevel([
            'level_order' => 1,
            'name' => 'المستوى الأول',
        ]);
        $level->forceFill(['matrix_id' => $matrix->id]);
        $level->save();

        expect($matrix->levels()->count())->toBe(1);
        expect($level->fresh()->matrix->id)->toBe($matrix->id);

        $authority = new PurchaseApproverAuthority([
            'max_amount' => '25000.50',
            'currency' => 'SAR',
            'covers_unpriced' => true,
        ]);
        $authority->forceFill([
            'user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'effective_from' => now(),
            'created_by' => $user->id,
        ]);
        $authority->save();
        $authority = $authority->fresh();

        expect($authority->covers_unpriced)->toBeTrue();
        expect($authority->max_amount)->toBe('25000.50');
        expect($authority->isRevoked())->toBeFalse();
    });

    withTenant($other, function () use ($matrix): void {
        expect(PurchaseApprovalMatrix::query()->whereKey($matrix->id)->exists())->toBeFalse();
    });
});

it('enforces unique level_order per matrix', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $user = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));

    $matrix = withTenant($tenant, function () use ($user): PurchaseApprovalMatrix {
        $m = new PurchaseApprovalMatrix(['name' => 'Unique levels']);
        $m->forceFill([
            'tenant_id' => $user->tenant_id,
            'status' => PurchaseApprovalMatrixStatus::Draft,
            'version_no' => 0,
            'created_by' => $user->id,
        ]);
        $m->save();

        return $m;
    });

    withTenant($tenant, function () use ($matrix): void {
        $first = new PurchaseApprovalMatrixLevel(['level_order' => 1, 'name' => 'L1']);
        $first->forceFill(['tenant_id' => $matrix->tenant_id, 'matrix_id' => $matrix->id]);
        $first->save();

        $dup = new PurchaseApprovalMatrixLevel(['level_order' => 1, 'name' => 'L1-dup']);
        $dup->forceFill(['tenant_id' => $matrix->tenant_id, 'matrix_id' => $matrix->id]);

        expect(fn () => $dup->save())->toThrow(QueryException::class);
    });
});

it('fails closed without tenant context when querying matrices', function (): void {
    expect(fn () => PurchaseApprovalMatrix::query()->count())
        ->toThrow(MissingTenantContextException::class);
});
