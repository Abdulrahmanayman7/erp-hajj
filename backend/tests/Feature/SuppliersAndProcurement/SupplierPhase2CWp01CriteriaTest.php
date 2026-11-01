<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Support\PublishedCriteriaResolver;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase II-C WP-01 — evaluation criteria foundations.
 *
 * Run (MySQL isolated):
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp01CriteriaTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('WP-01 criteria tests require MySQL erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   category: SupplierCategory,
 *   manager: User,
 *   publisher: User,
 *   viewer: User,
 *   base: string
 * }
 */
function iicWp01Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $category = withTenant($tenant, fn () => SupplierCategory::factory()->create());
    $manager = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.manage_criteria',
    ], $tenant);
    $publisher = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.approve_criteria',
    ], $tenant);
    $viewer = createSupplierUserWith(['supplier_evaluations.view'], $tenant);

    return [
        'tenant' => $tenant,
        'category' => $category,
        'manager' => $manager,
        'publisher' => $publisher,
        'viewer' => $viewer,
        'base' => '/api/v1/supplier-evaluation-criteria-packs',
    ];
}

/**
 * @param  list<array{name: string, weight: float|string}>  $lines
 */
function iicWp01SeedLines(string $base, int $packId, array $lines): void
{
    foreach ($lines as $i => $line) {
        spaPostJson("{$base}/{$packId}/criteria", [
            'name' => $line['name'],
            'weight' => $line['weight'],
            'sort_order' => $i + 1,
            'is_active' => $line['is_active'] ?? true,
        ])->assertCreated();
    }
}

test('WP01 unauthenticated criteria access returns 401', function (): void {
    iicWp01Context();
    spaGetJson('/api/v1/supplier-evaluation-criteria-packs')->assertUnauthorized();
    spaPostJson('/api/v1/supplier-evaluation-criteria-packs', [
        'supplier_category_id' => 1,
    ])->assertUnauthorized();
});

test('WP01 permission denial returns 403 for manage and publish', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['viewer']);

    spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertForbidden();

    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');

    iicWp01SeedLines($ctx['base'], (int) $id, [
        ['name' => 'جودة', 'weight' => 50],
        ['name' => 'التزام', 'weight' => 50],
    ]);

    spaPostJson("{$ctx['base']}/{$id}/publish")->assertForbidden();
});

test('WP01 draft lifecycle create edit criteria and reject invalid weights', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);

    $pack = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
        'notes' => 'مسودة أولى',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version_no', 1)
        ->json('data');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CRITERIA_PACK_CREATED,
    );

    spaPatchJson("{$ctx['base']}/{$pack['id']}", ['notes' => 'ملاحظات محدثة'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'ملاحظات محدثة');

    iicWp01SeedLines($ctx['base'], (int) $pack['id'], [
        ['name' => 'جودة', 'weight' => 40],
        ['name' => 'التزام', 'weight' => 40],
    ]);

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$pack['id']}/publish")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_INVALID_WEIGHTS');

    Sanctum::actingAs($ctx['manager']);
    $lineId = spaGetJson("{$ctx['base']}/{$pack['id']}/criteria")->assertOk()->json('data.0.id');
    spaPatchJson("{$ctx['base']}/{$pack['id']}/criteria/{$lineId}", ['weight' => 60])
        ->assertOk()
        ->assertJsonPath('data.weight', '60.0000');
});

test('WP01 weight sum boundaries accept ±0.01 and reject beyond', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);

    $okId = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');

    iicWp01SeedLines($ctx['base'], (int) $okId, [
        ['name' => 'أ', 'weight' => '50.005'],
        ['name' => 'ب', 'weight' => '49.995'],
    ]);

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$okId}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $cat2 = withTenant($ctx['tenant'], fn () => SupplierCategory::factory()->create());
    Sanctum::actingAs($ctx['manager']);
    $badId = spaPostJson($ctx['base'], [
        'supplier_category_id' => $cat2->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $badId, [
        ['name' => 'أ', 'weight' => '50.02'],
        ['name' => 'ب', 'weight' => '50.00'],
    ]);

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$badId}/publish")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_INVALID_WEIGHTS');
});

test('WP01 independent publish SoD blocks preparer and historical editor', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iicWp01Context();
    $editor = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.manage_criteria',
        'supplier_evaluations.approve_criteria',
    ], $ctx['tenant']);
    $preparerApprover = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.manage_criteria',
        'supplier_evaluations.approve_criteria',
    ], $ctx['tenant']);

    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $id, [
        ['name' => 'جودة', 'weight' => 100],
    ]);

    // User with both manage+approve who prepared the pack remains subject to SoD.
    Sanctum::actingAs($preparerApprover);
    $id2 = spaPostJson($ctx['base'], [
        'supplier_category_id' => withTenant($ctx['tenant'], fn () => SupplierCategory::factory()->create())->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $id2, [['name' => 'ك', 'weight' => 100]]);
    spaPostJson("{$ctx['base']}/{$id2}/publish")
        ->assertForbidden()
        ->assertJsonPath('code', 'CRITERIA_PACK_SOD_VIOLATION');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CRITERIA_PACK_SOD_VIOLATION,
    );

    // Historical editor (not preparer) cannot publish the same version.
    Sanctum::actingAs($editor);
    spaPatchJson("{$ctx['base']}/{$id}", ['notes' => 'تحرير تاريخي'])->assertOk();
    spaPostJson("{$ctx['base']}/{$id}/publish")
        ->assertForbidden()
        ->assertJsonPath('code', 'CRITERIA_PACK_SOD_VIOLATION');

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.approved_by.id', $ctx['publisher']->id);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CRITERIA_PACK_PUBLISHED,
    );
});

test('WP01 published pack is immutable and new draft version archives prior on publish', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $id, [['name' => 'جودة', 'weight' => 100]]);

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$id}/publish")->assertOk();

    Sanctum::actingAs($ctx['manager']);
    spaPatchJson("{$ctx['base']}/{$id}", ['notes' => 'لا'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_NOT_DRAFT');
    spaPostJson("{$ctx['base']}/{$id}/criteria", ['name' => 'x', 'weight' => 1])
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_NOT_DRAFT');

    $v2 = spaPostJson("{$ctx['base']}/{$id}/new-draft")
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version_no', 2)
        ->assertJsonPath('data.supersedes_pack_id', $id)
        ->json('data');

    expect($v2['criteria'])->toHaveCount(1);
    expect($v2['criteria'][0]['weight'])->toBe('100.0000');

    spaPatchJson("{$ctx['base']}/{$v2['id']}/criteria/{$v2['criteria'][0]['id']}", [
        'name' => 'جودة محدثة',
        'weight' => 100,
    ])->assertOk();

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$v2['id']}/publish")->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect(withTenant($ctx['tenant'], fn () => SupplierEvaluationCriteriaPack::query()->find($id)?->status))
        ->toBe(CriteriaPackStatus::Archived);

    $publishedCount = withTenant($ctx['tenant'], fn () => SupplierEvaluationCriteriaPack::query()
        ->where('supplier_category_id', $ctx['category']->id)
        ->where('status', CriteriaPackStatus::Published)
        ->count());
    expect($publishedCount)->toBe(1);
});

test('WP01 duplicate draft per category returns 409', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated();

    spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertStatus(409)
        ->assertJsonPath('code', 'CRITERIA_PACK_DRAFT_EXISTS');
});

test('WP01 cross-tenant pack access returns 404', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');

    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);
    $managerB = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.manage_criteria',
    ], $tenantB);
    Sanctum::actingAs($managerB);

    spaGetJson("{$ctx['base']}/{$id}")->assertNotFound();
    spaPatchJson("{$ctx['base']}/{$id}", ['notes' => 'x'])->assertNotFound();
    spaPostJson("{$ctx['base']}/{$id}/publish")->assertNotFound();
});

test('WP01 viewer sees published only; manage sees drafts', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    $draftId = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $draftId, [['name' => 'جودة', 'weight' => 100]]);

    Sanctum::actingAs($ctx['viewer']);
    $listed = spaGetJson($ctx['base'])->assertOk()->json('data');
    expect(collect($listed)->pluck('id')->all())->not->toContain($draftId);
    spaGetJson("{$ctx['base']}/{$draftId}")->assertForbidden();

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$draftId}/publish")->assertOk();

    Sanctum::actingAs($ctx['viewer']);
    spaGetJson("{$ctx['base']}/{$draftId}")->assertOk()->assertJsonPath('data.status', 'published');
    expect(collect(spaGetJson($ctx['base'])->json('data'))->pluck('id')->all())->toContain($draftId);
});

test('WP01 PublishedCriteriaResolver fails closed for missing invalid unpublished', function (): void {
    $ctx = iicWp01Context();
    $resolver = app(PublishedCriteriaResolver::class);

    withTenant($ctx['tenant'], function () use ($resolver, $ctx): void {
        expect($resolver->findPublishedForCategory((int) $ctx['category']->id))->toBeNull();

        $pack = SupplierEvaluationCriteriaPack::factory()->create([
            'supplier_category_id' => $ctx['category']->id,
            'prepared_by' => $ctx['manager']->id,
            'status' => CriteriaPackStatus::Draft,
        ]);
        SupplierEvaluationCriteriaPackFactory_withLines($pack, [
            ['name' => 'أ', 'weight' => 100],
        ]);

        expect($resolver->findPublishedForCategory((int) $ctx['category']->id))->toBeNull();
    });

    // Publish valid then corrupt weights under the hood should fail closed on resolve.
    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => withTenant($ctx['tenant'], fn () => SupplierCategory::factory()->create())->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $id, [
        ['name' => 'أ', 'weight' => 60],
        ['name' => 'ب', 'weight' => 40],
    ]);
    Sanctum::actingAs($ctx['publisher']);
    $published = spaPostJson("{$ctx['base']}/{$id}/publish")->assertOk()->json('data');

    withTenant($ctx['tenant'], function () use ($resolver, $published): void {
        $ready = $resolver->requirePublishedForCategory((int) $published['supplier_category_id']);
        expect($ready->id)->toBe($published['id']);

        // Bypass immutability guard by raw update to simulate corruption.
        DB::table('supplier_evaluation_criteria')
            ->where('pack_id', $published['id'])
            ->update(['weight' => 10]);

        expect($resolver->findPublishedForCategory((int) $published['supplier_category_id']))->toBeNull();
    });
});

/**
 * @param  list<array{name: string, weight: float|string}>  $lines
 */
function SupplierEvaluationCriteriaPackFactory_withLines(SupplierEvaluationCriteriaPack $pack, array $lines): void
{
    SupplierEvaluationCriteriaPackFactory::withLines($pack, $lines);
}

test('WP01 empty criteria cannot publish and validation rejects bad payload', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_EMPTY');

    Sanctum::actingAs($ctx['manager']);
    spaPostJson("{$ctx['base']}/{$id}/criteria", [
        'name' => '',
        'weight' => -1,
    ])->assertStatus(422);

    spaPostJson($ctx['base'], [])->assertStatus(422);
});

test('WP01 delete criterion on draft and replay publish on published returns not draft', function (): void {
    $ctx = iicWp01Context();
    Sanctum::actingAs($ctx['manager']);
    $id = spaPostJson($ctx['base'], [
        'supplier_category_id' => $ctx['category']->id,
    ])->assertCreated()->json('data.id');
    iicWp01SeedLines($ctx['base'], (int) $id, [
        ['name' => 'أ', 'weight' => 50],
        ['name' => 'ب', 'weight' => 50],
    ]);
    $lineId = spaGetJson("{$ctx['base']}/{$id}/criteria")->json('data.0.id');
    spaDeleteJson("{$ctx['base']}/{$id}/criteria/{$lineId}")->assertOk();
    expect(spaGetJson("{$ctx['base']}/{$id}/criteria")->json('data'))->toHaveCount(1);

    Sanctum::actingAs($ctx['manager']);
    spaPostJson("{$ctx['base']}/{$id}/criteria", ['name' => 'ج', 'weight' => 50])->assertCreated();

    Sanctum::actingAs($ctx['publisher']);
    spaPostJson("{$ctx['base']}/{$id}/publish")->assertOk();
    spaPostJson("{$ctx['base']}/{$id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CRITERIA_PACK_NOT_DRAFT');
});
