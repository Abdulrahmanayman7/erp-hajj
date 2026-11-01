<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase IV WP01-E — matrix/authority configuration API.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase4Wp01eApiTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP01-E tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   admin: User,
 *   viewer: User,
 *   subject: User,
 *   unit: OrganizationUnit,
 *   matrices: string,
 *   authorities: string
 * }
 */
function ivWp01eContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $admin = createSupplierUserWith(['purchase_requests.manage_approval_config'], $tenant);
    $viewer = createSupplierUserWith(['purchase_requests.view'], $tenant);
    $subject = withTenant($tenant, fn () => User::factory()->create(['tenant_id' => $tenant->id]));
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    return [
        'tenant' => $tenant,
        'admin' => $admin,
        'viewer' => $viewer,
        'subject' => $subject,
        'unit' => $unit,
        'matrices' => '/api/v1/purchase-approval-matrices',
        'authorities' => '/api/v1/purchase-approver-authorities',
    ];
}

it('creates matrix, rules, levels and publishes via API', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['admin']);

    $created = spaPostJson($ctx['matrices'], ['name' => 'مصفوفة API'])
        ->assertCreated()
        ->json('data');

    expect($created['status'])->toBe(PurchaseApprovalMatrixStatus::Draft->value)
        ->and($created['name'])->toBe('مصفوفة API');

    $matrixId = $created['id'];

    spaPostJson("{$ctx['matrices']}/{$matrixId}/levels", [
        'level_order' => 1,
        'name' => 'المستوى الأول',
    ])->assertCreated();

    spaPostJson("{$ctx['matrices']}/{$matrixId}/rules", [
        'applies_when_unpriced' => true,
        'min_amount' => 0,
        'max_amount' => 50000,
    ])->assertCreated();

    $published = spaPostJson("{$ctx['matrices']}/{$matrixId}/publish", [])
        ->assertOk()
        ->json('data');

    expect($published['status'])->toBe(PurchaseApprovalMatrixStatus::Published->value)
        ->and($published['version_no'])->toBe(1)
        ->and($published['rules'])->not->toBeEmpty()
        ->and($published['levels'])->not->toBeEmpty();
});

it('denies matrix and authority APIs without manage_approval_config (403)', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['viewer']);

    spaGetJson($ctx['matrices'])->assertForbidden();
    spaPostJson($ctx['matrices'], ['name' => 'ممنوع'])->assertForbidden();
    spaGetJson($ctx['authorities'])->assertForbidden();
    spaPostJson($ctx['authorities'], [
        'user_id' => $ctx['subject']->id,
        'max_amount' => 1000,
        'covers_unpriced' => false,
        'effective_from' => now()->toIso8601String(),
    ])->assertForbidden();
});

it('returns 404 for cross-tenant matrix and authority ids', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['admin']);

    $matrixId = spaPostJson($ctx['matrices'], ['name' => 'محلي'])->assertCreated()->json('data.id');
    $authorityId = spaPostJson($ctx['authorities'], [
        'user_id' => $ctx['subject']->id,
        'max_amount' => 7500,
        'covers_unpriced' => true,
        'effective_from' => now()->toIso8601String(),
        'organization_unit_id' => $ctx['unit']->id,
    ])->assertCreated()->json('data.id');

    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($other);
    $otherAdmin = createSupplierUserWith(['purchase_requests.manage_approval_config'], $other);
    Sanctum::actingAs($otherAdmin);

    spaGetJson("{$ctx['matrices']}/{$matrixId}")->assertNotFound();
    spaPostJson("{$ctx['matrices']}/{$matrixId}/publish", [])->assertNotFound();
    spaGetJson("{$ctx['authorities']}/{$authorityId}")->assertNotFound();
    spaPostJson("{$ctx['authorities']}/{$authorityId}/revoke", [])->assertNotFound();
});

it('grants updates and revokes authority via API', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['admin']);

    $granted = spaPostJson($ctx['authorities'], [
        'user_id' => $ctx['subject']->id,
        'max_amount' => 12000.25,
        'covers_unpriced' => true,
        'effective_from' => now()->toIso8601String(),
    ])->assertCreated()->json('data');

    expect($granted['covers_unpriced'])->toBeTrue()
        ->and($granted['max_amount'])->toBe('12000.25')
        ->and($granted['revoked_at'])->toBeNull();

    $updated = spaPatchJson("{$ctx['authorities']}/{$granted['id']}", [
        'max_amount' => 20000,
        'covers_unpriced' => false,
    ])->assertOk()->json('data');

    expect($updated['max_amount'])->toBe('20000.00')
        ->and($updated['covers_unpriced'])->toBeFalse();

    $revoked = spaPostJson("{$ctx['authorities']}/{$granted['id']}/revoke", [])
        ->assertOk()
        ->json('data');

    expect($revoked['revoked_at'])->not->toBeNull();

    withTenant($ctx['tenant'], function () use ($granted): void {
        expect(PurchaseApproverAuthority::query()->whereKey($granted['id'])->firstOrFail()->isRevoked())->toBeTrue();
    });
});

it('rejects overlapping authority grant via API with AUTHORITY_OVERLAP', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['admin']);

    spaPostJson($ctx['authorities'], [
        'user_id' => $ctx['subject']->id,
        'max_amount' => 5000,
        'covers_unpriced' => false,
        'effective_from' => now()->subDay()->toIso8601String(),
        'effective_to' => now()->addWeek()->toIso8601String(),
    ])->assertCreated();

    spaPostJson($ctx['authorities'], [
        'user_id' => $ctx['subject']->id,
        'max_amount' => 8000,
        'covers_unpriced' => true,
        'effective_from' => now()->toIso8601String(),
    ])->assertStatus(422)
        ->assertJsonPath('code', 'AUTHORITY_OVERLAP');
});

it('lists matrices filtered by status for config admin', function (): void {
    $ctx = ivWp01eContext();
    Sanctum::actingAs($ctx['admin']);

    $draftId = spaPostJson($ctx['matrices'], ['name' => 'مسودة قائمة'])->assertCreated()->json('data.id');
    spaPostJson("{$ctx['matrices']}/{$draftId}/levels", ['level_order' => 1, 'name' => 'L1'])->assertCreated();
    spaPostJson("{$ctx['matrices']}/{$draftId}/rules", ['applies_when_unpriced' => true])->assertCreated();
    spaPostJson("{$ctx['matrices']}/{$draftId}/publish", [])->assertOk();

    spaPostJson($ctx['matrices'], ['name' => 'ما زالت مسودة'])->assertCreated();

    $drafts = spaGetJson($ctx['matrices'].'?status=draft')->assertOk()->json('data');
    $published = spaGetJson($ctx['matrices'].'?status=published')->assertOk()->json('data');

    expect(collect($drafts)->pluck('status')->unique()->all())->toBe(['draft'])
        ->and(collect($published)->pluck('status')->unique()->all())->toBe(['published'])
        ->and(collect($published)->pluck('id')->all())->toContain($draftId);

    withTenant($ctx['tenant'], function () use ($draftId): void {
        expect(PurchaseApprovalMatrix::query()->whereKey($draftId)->firstOrFail()->isPublished())->toBeTrue();
    });
});
