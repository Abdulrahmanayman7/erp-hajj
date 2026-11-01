<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Support\EvaluationNumberGenerator;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase II-C WP-02–WP-06 — evaluations, appeals, notifications.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp02to06EvaluationsTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('II-C evaluation tests require MySQL erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   supplier: Supplier,
 *   category: SupplierCategory,
 *   pack: SupplierEvaluationCriteriaPack,
 *   criterionId: int,
 *   preparer: User,
 *   approver: User,
 *   appealer: User,
 *   viewer: User,
 *   base: string
 * }
 */
function iicEvalContext(bool $withPublishedPack = true): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    [$supplier, $category, $pack, $criterionId] = withTenant($tenant, function () use ($tenant, $withPublishedPack) {
        $category = SupplierCategory::factory()->create();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        $supplier->categories()->attach($category->id, ['tenant_id' => $supplier->tenant_id]);

        $pack = null;
        $criterionId = 0;
        if ($withPublishedPack) {
            $author = createSupplierUserWith(['supplier_evaluations.manage_criteria'], $tenant);
            $pack = SupplierEvaluationCriteriaPack::factory()->create([
                'supplier_category_id' => $category->id,
                'prepared_by' => $author->id,
                'last_edited_by' => $author->id,
                'status' => CriteriaPackStatus::Draft,
                'version_no' => 1,
            ]);
            SupplierEvaluationCriteriaPackFactory::withLines($pack, [
                ['name' => 'جودة', 'weight' => 60],
                ['name' => 'التزام', 'weight' => 40],
            ]);
            $pack->forceFill([
                'status' => CriteriaPackStatus::Published,
                'approved_by' => $author->id,
                'approved_at' => now(),
            ])->save();
            $pack = $pack->fresh(['criteria']);
            $criterionId = (int) $pack->criteria->first()->id;
        }

        return [$supplier, $category, $pack, $criterionId];
    });

    $preparer = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
    ], $tenant);
    $approver = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.approve',
    ], $tenant);
    $appealer = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.handle_appeal',
        'supplier_evaluations.prepare',
    ], $tenant);
    $viewer = createSupplierUserWith(['supplier_evaluations.view'], $tenant);

    return [
        'tenant' => $tenant,
        'supplier' => $supplier,
        'category' => $category,
        'pack' => $pack,
        'criterionId' => $criterionId,
        'preparer' => $preparer,
        'approver' => $approver,
        'appealer' => $appealer,
        'viewer' => $viewer,
        'base' => '/api/v1/supplier-evaluations',
    ];
}

/**
 * @return list<array{criterion_id: int, score: float|int}>
 */
function iicFullScores(array $ctx, float $score = 80): array
{
    /** @var SupplierEvaluationCriteriaPack $pack */
    $pack = $ctx['pack'];

    return withTenant($ctx['tenant'], function () use ($pack, $score): array {
        return $pack->fresh()->activeCriteria()->orderBy('id')->get()->map(
            fn ($c) => ['criterion_id' => (int) $c->id, 'score' => $score],
        )->all();
    });
}

function iicCreateDraft(array $ctx, array $overrides = []): array
{
    Sanctum::actingAs($ctx['preparer']);

    return spaPostJson($ctx['base'], array_merge([
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'qualification',
        'monitoring_recommended' => false,
        'scores' => iicFullScores($ctx),
    ], $overrides))->assertCreated()->json('data');
}

function iicSubmitPending(array $ctx, array $overrides = []): array
{
    $draft = iicCreateDraft($ctx, $overrides);
    spaPostJson("{$ctx['base']}/{$draft['id']}/submit")->assertOk();

    return spaGetJson("{$ctx['base']}/{$draft['id']}")->assertOk()->json('data');
}

test('IIC evaluation sequence rolls back with surrounding transaction', function (): void {
    $ctx = iicEvalContext();
    withTenant($ctx['tenant'], function () use ($ctx): void {
        try {
            DB::transaction(function (): void {
                $n = app(EvaluationNumberGenerator::class)->next();
                expect($n)->toBe('SEV-000001');
                throw new RuntimeException('force-sev-rollback');
            });
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('force-sev-rollback');
        }
        expect(DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $ctx['tenant']->id)->value('next_value'))->toBeNull();
        expect(app(EvaluationNumberGenerator::class)->next())->toBe('SEV-000001');
    });
});

test('IIC failed create preconditions do not consume evaluation numbers', function (): void {
    $ctx = iicEvalContext(withPublishedPack: false);
    Sanctum::actingAs($ctx['preparer']);
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'qualification',
    ])->assertStatus(422);
    expect(DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $ctx['tenant']->id)->value('next_value'))->toBeNull();
    expect(DB::table('supplier_evaluations')->where('tenant_id', $ctx['tenant']->id)->count())->toBe(0);
});

test('IIC unauthenticated evaluations return 401', function (): void {
    iicEvalContext();
    spaGetJson('/api/v1/supplier-evaluations')->assertUnauthorized();
});

test('IIC missing prepare permission returns 403 on create', function (): void {
    $ctx = iicEvalContext();
    Sanctum::actingAs($ctx['viewer']);
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'qualification',
    ])->assertForbidden();
});

test('IIC draft submit approve lifecycle with score snapshot immutability AC-E01', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iicEvalContext();
    // Second approve-holder receives monitoring notify (actor is excluded per Eng IIC-F2).
    createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.approve',
    ], $ctx['tenant']);

    $draft = iicCreateDraft($ctx, ['monitoring_recommended' => true]);
    expect($draft['status'])->toBe('draft');

    Sanctum::actingAs($ctx['preparer']);
    $pending = spaPostJson("{$ctx['base']}/{$draft['id']}/submit")->assertOk()->json('data');
    expect($pending['status'])->toBe('pending_approval')
        ->and($pending['total_score'])->toBe('80.00')
        ->and($pending['score_snapshots'])->toHaveCount(2);

    $notifBefore = withTenant(
        $ctx['tenant'],
        fn () => Notification::query()->where('type', NotificationType::SupplierEvaluationMonitoringRecommended)->count(),
    );

    Sanctum::actingAs($ctx['approver']);
    $approved = spaPostJson("{$ctx['base']}/{$draft['id']}/approve", [
        'monitoring_recommended' => true,
    ])->assertOk()->json('data');

    expect($approved['status'])->toBe('approved')
        ->and($approved['monitoring_recommended'])->toBeTrue();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EVALUATION_APPROVED,
    );

    $before = $approved['score_snapshots'][0]['score'];

    withTenant($ctx['tenant'], function () use ($ctx): void {
        $ctx['pack']->criteria()->first()->update(['weight' => 50]);
    });

    $after = spaGetJson("{$ctx['base']}/{$draft['id']}")->json('data');
    expect($after['score_snapshots'][0]['score'])->toBe($before)
        ->and($after['total_score'])->toBe('80.00');

    expect(withTenant(
        $ctx['tenant'],
        fn () => Notification::query()->where('type', NotificationType::SupplierEvaluationMonitoringRecommended)->count(),
    ))->toBeGreaterThan($notifBefore);
});

test('IIC post_season requires season; other kinds optional AC-E02 IIC-F1', function (): void {
    $ctx = iicEvalContext();
    Sanctum::actingAs($ctx['preparer']);

    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'post_season',
        'scores' => iicFullScores($ctx),
    ])->assertStatus(422)->assertJsonPath('code', 'EVALUATION_SEASON_REQUIRED');

    $season = withTenant($ctx['tenant'], fn () => Season::factory()->create());
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'post_season',
        'season_id' => $season->id,
        'scores' => iicFullScores($ctx),
    ])->assertCreated();

    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'periodic',
        'scores' => iicFullScores($ctx),
    ])->assertCreated();
});

test('IIC preparer cannot approve or reject AC-E03 SoD', function (): void {
    $ctx = iicEvalContext();
    // SoD must hold even when the preparer also holds the sensitive approve permission.
    $dual = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
        'supplier_evaluations.approve',
    ], $ctx['tenant']);
    $ctx['preparer'] = $dual;

    $pending = iicSubmitPending($ctx);

    Sanctum::actingAs($dual);
    spaPostJson("{$ctx['base']}/{$pending['id']}/approve")->assertStatus(403)
        ->assertJsonPath('code', 'EVALUATION_SOD_VIOLATION');
    spaPostJson("{$ctx['base']}/{$pending['id']}/reject", [
        'reason' => 'سبب رفض كافٍ للاختبار',
    ])->assertStatus(403)->assertJsonPath('code', 'EVALUATION_SOD_VIOLATION');
});

test('IIC monitoring proposal does not notify; approve false does not notify AC-E04 IIC-F3', function (): void {
    $ctx = iicEvalContext();
    Sanctum::actingAs($ctx['preparer']);
    $draft = iicCreateDraft($ctx, ['monitoring_recommended' => true]);
    spaPostJson("{$ctx['base']}/{$draft['id']}/submit")->assertOk();

    $count = withTenant(
        $ctx['tenant'],
        fn () => Notification::query()->where('type', NotificationType::SupplierEvaluationMonitoringRecommended)->count(),
    );

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("{$ctx['base']}/{$draft['id']}/approve", [
        'monitoring_recommended' => false,
    ])->assertOk();

    expect(withTenant(
        $ctx['tenant'],
        fn () => Notification::query()->where('type', NotificationType::SupplierEvaluationMonitoringRecommended)->count(),
    ))->toBe($count);
});

test('IIC appeal accept spawns new draft version; prior immutable AC-E05', function (): void {
    $ctx = iicEvalContext();
    $pending = iicSubmitPending($ctx);
    Sanctum::actingAs($ctx['approver']);
    spaPostJson("{$ctx['base']}/{$pending['id']}/approve")->assertOk();

    Sanctum::actingAs($ctx['preparer']);
    $appeal = spaPostJson("{$ctx['base']}/{$pending['id']}/appeals", [
        'reason' => 'سبب اعتراض كافٍ للقبول',
    ])->assertCreated()->json('data');

    Sanctum::actingAs($ctx['appealer']);
    $accepted = spaPostJson("/api/v1/supplier-evaluation-appeals/{$appeal['id']}/accept")
        ->assertOk()
        ->json('data');

    expect($accepted['status'])->toBe('accepted')
        ->and($accepted['resulting_evaluation_id'])->not->toBeNull();

    $correction = spaGetJson("{$ctx['base']}/{$accepted['resulting_evaluation_id']}")->assertOk()->json('data');
    expect($correction['status'])->toBe('draft')
        ->and($correction['version_no'])->toBe(2)
        ->and($correction['supersedes_evaluation_id'])->toBe($pending['id'])
        ->and($correction['evaluation_family_key'])->toBe($pending['evaluation_family_key'])
        ->and($correction['score_snapshots'] ?? [])->toBeEmpty();

    Sanctum::actingAs($ctx['preparer']);
    spaPatchJson("{$ctx['base']}/{$pending['id']}", ['notes' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'EVALUATION_NOT_DRAFT');
});

test('IIC cross-tenant evaluation access returns 404 SEC-T1', function (): void {
    $ctx = iicEvalContext();
    $draft = iicCreateDraft($ctx);

    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);
    $userB = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
        'supplier_evaluations.approve',
    ], $tenantB);

    Sanctum::actingAs($userB);
    spaGetJson("{$ctx['base']}/{$draft['id']}")->assertNotFound();
    spaPostJson("{$ctx['base']}/{$draft['id']}/submit")->assertNotFound();
});

test('IIC missing published criteria fail-closed SEC-T4', function (): void {
    $ctx = iicEvalContext(withPublishedPack: false);
    Sanctum::actingAs($ctx['preparer']);
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'qualification',
    ])->assertStatus(422)->assertJsonPath('code', 'EVALUATION_CRITERIA_MISSING');
});

test('IIC archived supplier and unlinked category rejected', function (): void {
    $ctx = iicEvalContext();
    withTenant($ctx['tenant'], function () use ($ctx): void {
        $ctx['supplier']->forceFill(['status' => SupplierStatus::Archived])->save();
    });

    Sanctum::actingAs($ctx['preparer']);
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'qualification',
        'scores' => iicFullScores($ctx),
    ])->assertStatus(422)->assertJsonPath('code', 'EVALUATION_SUPPLIER_ARCHIVED');

    $ctx2 = iicEvalContext();
    $otherCategory = withTenant($ctx2['tenant'], fn () => SupplierCategory::factory()->create());
    Sanctum::actingAs($ctx2['preparer']);
    spaPostJson($ctx2['base'], [
        'supplier_id' => $ctx2['supplier']->id,
        'supplier_category_id' => $otherCategory->id,
        'evaluation_kind' => 'qualification',
        'scores' => iicFullScores($ctx2),
    ])->assertStatus(422)->assertJsonPath('code', 'EVALUATION_CATEGORY_NOT_LINKED');
});

test('IIC appeal SoD blocks filer and original preparer SEC-T3', function (): void {
    $ctx = iicEvalContext();
    $pending = iicSubmitPending($ctx);
    Sanctum::actingAs($ctx['approver']);
    spaPostJson("{$ctx['base']}/{$pending['id']}/approve")->assertOk();

    // Filer with handle_appeal still cannot accept their own appeal (SoD).
    Sanctum::actingAs($ctx['appealer']);
    $appeal = spaPostJson("{$ctx['base']}/{$pending['id']}/appeals", [
        'reason' => 'سبب اعتراض كافٍ للرفض',
    ])->assertCreated()->json('data');

    Sanctum::actingAs($ctx['appealer']);
    spaPostJson("/api/v1/supplier-evaluation-appeals/{$appeal['id']}/accept")
        ->assertStatus(403)
        ->assertJsonPath('code', 'EVALUATION_APPEAL_SOD_VIOLATION');

    // Original evaluation preparer holding handle_appeal is also blocked.
    $prepWithAppeal = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
        'supplier_evaluations.handle_appeal',
    ], $ctx['tenant']);
    withTenant($ctx['tenant'], function () use ($pending, $prepWithAppeal): void {
        SupplierEvaluation::query()->whereKey($pending['id'])->update([
            'prepared_by' => $prepWithAppeal->id,
        ]);
    });

    Sanctum::actingAs($prepWithAppeal);
    spaPostJson("/api/v1/supplier-evaluation-appeals/{$appeal['id']}/accept")
        ->assertStatus(403)
        ->assertJsonPath('code', 'EVALUATION_APPEAL_SOD_VIOLATION');

    Sanctum::actingAs($ctx['appealer']);
    spaPostJson("{$ctx['base']}/{$pending['id']}/appeals", [
        'reason' => 'اعتراض ثانٍ بعد إغلاق الأول غير ممكن',
    ])->assertStatus(409);

    $independent = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.handle_appeal',
    ], $ctx['tenant']);
    Sanctum::actingAs($independent);
    spaPostJson("/api/v1/supplier-evaluation-appeals/{$appeal['id']}/reject", [
        'review_outcome_note' => 'مرفوض',
    ])->assertOk();
});

test('IIC reject pending evaluation and double approve returns 409', function (): void {
    $ctx = iicEvalContext();
    $pending = iicSubmitPending($ctx);

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("{$ctx['base']}/{$pending['id']}/reject", [
        'reason' => 'سبب رفض كافٍ للاختبار الآلي',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');

    spaPostJson("{$ctx['base']}/{$pending['id']}/approve")->assertStatus(409)
        ->assertJsonPath('code', 'EVALUATION_INVALID_STATUS');
});

test('IIC scoring formula uses weighted average half-up', function (): void {
    $ctx = iicEvalContext();
    Sanctum::actingAs($ctx['preparer']);
    $scores = withTenant($ctx['tenant'], function () use ($ctx): array {
        $out = [];
        foreach ($ctx['pack']->fresh()->activeCriteria()->orderBy('sort_order')->get() as $i => $c) {
            $out[] = ['criterion_id' => (int) $c->id, 'score' => $i === 0 ? 100 : 50];
        }

        return $out;
    });
    // 100*0.6 + 50*0.4 = 80.00
    $draft = iicCreateDraft($ctx, ['scores' => $scores]);
    $pending = spaPostJson("{$ctx['base']}/{$draft['id']}/submit")->assertOk()->json('data');
    expect($pending['total_score'])->toBe('80.00');
});

test('IIC cross-tenant season rejected', function (): void {
    $ctx = iicEvalContext();
    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);
    $seasonB = withTenant($tenantB, fn () => Season::factory()->create());

    Sanctum::actingAs($ctx['preparer']);
    spaPostJson($ctx['base'], [
        'supplier_id' => $ctx['supplier']->id,
        'supplier_category_id' => $ctx['category']->id,
        'evaluation_kind' => 'post_season',
        'season_id' => $seasonB->id,
        'scores' => iicFullScores($ctx),
    ])->assertStatus(404)->assertJsonPath('code', 'EVALUATION_SEASON_INVALID');
});
