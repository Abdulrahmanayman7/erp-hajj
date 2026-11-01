<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestVersion;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase III WP-01–WP-05 — purchase requests (G1–G5).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase3PurchaseRequestsTest.php
 */

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Phase III PR tests require MySQL erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{
 *   tenant: Tenant,
 *   unit: OrganizationUnit,
 *   creator: \App\Models\User,
 *   viewer: \App\Models\User,
 *   financial: \App\Models\User,
 *   base: string
 * }
 */
function iiiPrContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $creator = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
        'purchase_requests.cancel',
        'purchase_requests.verify_priority',
    ], $tenant);

    $viewer = createSupplierUserWith(['purchase_requests.view'], $tenant);

    $financial = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.financial_review',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'creator' => $creator,
        'viewer' => $viewer,
        'financial' => $financial,
        'base' => '/api/v1/purchase-requests',
    ];
}

/**
 * @return array<string, mixed>
 */
function iiiPrPayload(OrganizationUnit $unit, array $overrides = []): array
{
    return array_merge([
        'request_kind' => 'administrative',
        'season_id' => null,
        'title' => 'طلب توريد مستلزمات',
        'purpose' => 'تغطية احتياجات الموسم التشغيلي',
        'priority' => 'normal',
        'urgency_justification' => null,
        'beneficiary_organization_unit_id' => $unit->id,
        'optional_supplier_id' => null,
        'currency' => 'SAR',
        'lines' => [
            [
                'line_no' => 1,
                'description' => 'خيم قماشية',
                'quantity' => 10,
                'unit' => 'قطعة',
                'need_date' => now()->addDays(14)->toDateString(),
                'unit_estimate' => 100,
            ],
        ],
    ], $overrides);
}

it('creates draft PR with number, totals, and audit', function (): void {
    $ctx = iiiPrContext();
    Event::fake([AuthorizationSecurityEvent::class]);
    Sanctum::actingAs($ctx['creator']);

    $response = $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.pending_approval_engine', false)
        ->assertJsonPath('data.matrix_id', null);

    expect($response->json('data.pr_number'))->toMatch('/^PR-\d{6}$/');
    expect((float) $response->json('data.estimated_total_priced'))->toBe(1000.0);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_CREATED,
    );
});

it('rejects unauthenticated create with 401', function (): void {
    $ctx = iiiPrContext();

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->assertUnauthorized();
});

it('rejects viewer without create permission with 403', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['viewer']);

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->assertForbidden();
});

it('returns 404 for cross-tenant purchase request access', function (): void {
    $ctxA = iiiPrContext();
    $ctxB = iiiPrContext();
    Sanctum::actingAs($ctxA['creator']);

    $create = $this->postJson($ctxA['base'], iiiPrPayload($ctxA['unit']));
    $create->assertCreated();
    $id = (int) $create->json('data.id');

    Sanctum::actingAs($ctxB['creator']);
    $this->getJson($ctxA['base'].'/'.$id)->assertNotFound();
});

it('updates draft header and replaces lines', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    $this->patchJson($ctx['base'].'/'.$id, [
        'title' => 'عنوان محدّث',
        'purpose' => 'غرض محدّث للطلب',
        'priority' => 'urgent',
        'urgency_justification' => 'حاجة تشغيلية عاجلة موثقة',
    ])->assertOk()->assertJsonPath('data.title', 'عنوان محدّث');

    $this->putJson($ctx['base'].'/'.$id.'/lines', [
        'lines' => [
            [
                'description' => 'بند غير مسعّر',
                'quantity' => 2,
                'unit' => 'طقم',
                'need_date' => now()->addDays(7)->toDateString(),
                'unpriced_reason' => 'سعر السوق غير مستقر حالياً',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.has_unpriced_lines', true)
        ->assertJsonPath('data.lines.0.unpriced_reason', 'سعر السوق غير مستقر حالياً');
});

it('submits to submitted with version snapshot and pending_approval_engine', function (): void {
    $ctx = iiiPrContext();
    Event::fake([AuthorizationSecurityEvent::class]);
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    $submit = $this->postJson($ctx['base'].'/'.$id.'/submit', []);
    $submit->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.pending_approval_engine', true)
        ->assertJsonPath('data.matrix_id', null)
        ->assertJsonPath('data.version_no', 1);

    withTenant($ctx['tenant'], function () use ($id): void {
        expect(PurchaseRequestVersion::query()->where('purchase_request_id', $id)->count())->toBe(1);
        $version = PurchaseRequestVersion::query()->where('purchase_request_id', $id)->first();
        expect($version->lines()->count())->toBe(1);
    });

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_SUBMITTED,
    );

    $versions = $this->getJson($ctx['base'].'/'.$id.'/versions?include=lines');
    $versions->assertOk()->assertJsonPath('data.0.version_no', 1);
});

it('blocks double submit with 409', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])
        ->assertStatus(409)
        ->assertJsonPath('code', 'PR_INVALID_TRANSITION');
});

it('requires duplicate acknowledgement when hints exist', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $firstId = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'title' => 'طلب مكرر للاختبار',
    ]))->json('data.id');
    $this->postJson($ctx['base'].'/'.$firstId.'/submit', [])->assertOk();

    $secondId = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'title' => 'طلب مكرر للاختبار',
    ]))->json('data.id');

    $this->postJson($ctx['base'].'/'.$secondId.'/submit', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PR_DUPLICATE_ACK_REQUIRED');

    $this->postJson($ctx['base'].'/'.$secondId.'/submit', [
        'acknowledge_duplicate_warning' => true,
    ])->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('cancels non-approved request with reason', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    $this->postJson($ctx['base'].'/'.$id.'/cancel', [
        'reason' => 'لم تعد الحاجة قائمة لهذا الطلب',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('verifies priority without changing status', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'priority' => 'emergency',
        'urgency_justification' => 'حالة طارئة موثقة للموسم',
    ]))->json('data.id');

    $this->postJson($ctx['base'].'/'.$id.'/verify-priority', [])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.priority_verified_by.id', $ctx['creator']->id);
});

it('records financial review bound to version_no', function (): void {
    $ctx = iiiPrContext();
    Event::fake([AuthorizationSecurityEvent::class]);
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();

    Sanctum::actingAs($ctx['financial']);
    $this->postJson($ctx['base'].'/'.$id.'/financial-reviews', [
        'outcome' => 'cleared',
        'justification' => 'المراجعة المالية مكتملة والمبالغ متسقة',
        'version_no' => 1,
    ])->assertCreated()->assertJsonPath('data.outcome', 'cleared');

    withTenant($ctx['tenant'], function () use ($id): void {
        expect(PurchaseRequestFinancialReview::query()->where('purchase_request_id', $id)->count())->toBe(1);
    });

    Sanctum::actingAs($ctx['financial']);
    $this->postJson($ctx['base'].'/'.$id.'/financial-reviews', [
        'outcome' => 'cleared',
        'justification' => 'محاولة على إصدار خاطئ عمداً هنا',
        'version_no' => 99,
    ])->assertStatus(422)->assertJsonPath('code', 'PR_FINANCIAL_REVIEW_VERSION_MISMATCH');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PR_FINANCIAL_REVIEW_RECORDED,
    );
});

it('denies financial_review without explicit permission', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    $this->postJson($ctx['base'].'/'.$id.'/financial-reviews', [
        'outcome' => 'cleared',
        'justification' => 'محاولة بدون صلاحية المراجعة المالية',
        'version_no' => 1,
    ])->assertForbidden();
});

it('blocks edit after submit', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();

    $this->patchJson($ctx['base'].'/'.$id, [
        'title' => 'لا يجب التعديل',
    ])->assertStatus(422)->assertJsonPath('code', 'PR_EDIT_LOCKED');
});

it('rejects archived optional supplier', function (): void {
    $ctx = iiiPrContext();
    $supplier = withTenant($ctx['tenant'], fn () => Supplier::factory()->create([
        'status' => SupplierStatus::Archived,
    ]));

    Sanctum::actingAs($ctx['creator']);
    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'optional_supplier_id' => $supplier->id,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_SUPPLIER_ARCHIVED');
});

it('allocates unique PR numbers under concurrent allocation', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $numbers = [];
    for ($i = 0; $i < 5; $i++) {
        $numbers[] = $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
            'title' => "طلب رقم {$i}",
        ]))->json('data.pr_number');
    }

    expect(count(array_unique($numbers)))->toBe(5);
});

it('keeps independent PR number sequences per tenant', function (): void {
    $ctxA = iiiPrContext();
    $ctxB = iiiPrContext();

    Sanctum::actingAs($ctxA['creator']);
    $a1 = $this->postJson($ctxA['base'], iiiPrPayload($ctxA['unit']))->assertCreated()->json('data.pr_number');
    $a2 = $this->postJson($ctxA['base'], iiiPrPayload($ctxA['unit'], ['title' => 'ثاني']))->assertCreated()->json('data.pr_number');

    Sanctum::actingAs($ctxB['creator']);
    $b1 = $this->postJson($ctxB['base'], iiiPrPayload($ctxB['unit']))->assertCreated()->json('data.pr_number');

    expect($a1)->toBe('PR-000001')
        ->and($a2)->toBe('PR-000002')
        ->and($b1)->toBe('PR-000001');
});

it('rolls back PR sequence allocation when the surrounding transaction rolls back', function (): void {
    $ctx = iiiPrContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        try {
            DB::transaction(function (): void {
                $number = app(\App\Modules\SuppliersAndProcurement\Support\PurchaseRequestNumberGenerator::class)->next();
                expect($number)->toBe('PR-000001');
                throw new RuntimeException('force-sequence-rollback');
            });
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('force-sequence-rollback');
        }

        $seq = DB::table('purchase_request_number_sequences')
            ->where('tenant_id', $ctx['tenant']->id)
            ->value('next_value');
        expect($seq)->toBeNull();

        $reissued = app(\App\Modules\SuppliersAndProcurement\Support\PurchaseRequestNumberGenerator::class)->next();
        expect($reissued)->toBe('PR-000001');
    });
});

it('does not advance PR sequence when create preconditions fail', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);
    $inactive = withTenant($ctx['tenant'], fn () => OrganizationUnit::factory()->inactive()->create());

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'beneficiary_organization_unit_id' => $inactive->id,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_ORG_UNIT_INVALID');

    $seq = DB::table('purchase_request_number_sequences')
        ->where('tenant_id', $ctx['tenant']->id)
        ->value('next_value');
    expect($seq)->toBeNull();
    expect(DB::table('purchase_requests')->where('tenant_id', $ctx['tenant']->id)->count())->toBe(0);
});

it('keeps version snapshots immutable', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();

    withTenant($ctx['tenant'], function () use ($id): void {
        $version = PurchaseRequestVersion::query()->where('purchase_request_id', $id)->firstOrFail();
        expect(fn () => $version->forceFill(['title' => 'mutated'])->save())
            ->toThrow(LogicException::class);
    });
});

it('lists purchase requests for viewer', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);
    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->assertCreated();

    Sanctum::actingAs($ctx['viewer']);
    $this->getJson($ctx['base'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('does not seed approval engine matrix on submit', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    withTenant($ctx['tenant'], function () use ($id): void {
        $pr = PurchaseRequest::query()->findOrFail($id);
        expect($pr->status)->toBe(PurchaseRequestStatus::Draft);
    });

    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();

    withTenant($ctx['tenant'], function () use ($id): void {
        $pr = PurchaseRequest::query()->findOrFail($id);
        expect($pr->status)->toBe(PurchaseRequestStatus::Submitted);
        expect($pr->matrix_id)->toBeNull();
        expect($pr->matrix_version_no)->toBeNull();
    });
});

it('denies list without purchase_requests.view even when create is held', function (): void {
    $ctx = iiiPrContext();
    $creatorOnly = createSupplierUserWith(['purchase_requests.create'], $ctx['tenant']);
    Sanctum::actingAs($creatorOnly);

    $this->getJson($ctx['base'])->assertForbidden();
});

it('requires season for seasonal and rejects season on administrative', function (): void {
    $ctx = iiiPrContext();
    $season = withTenant($ctx['tenant'], fn () => Season::factory()->active()->create());
    Sanctum::actingAs($ctx['creator']);

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'request_kind' => 'seasonal',
        'season_id' => null,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_SEASON_REQUIRED');

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'request_kind' => 'seasonal',
        'season_id' => $season->id,
        'title' => 'طلب موسمي',
    ]))->assertCreated()->assertJsonPath('data.season_id', $season->id);

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'request_kind' => 'administrative',
        'season_id' => $season->id,
    ]))->assertStatus(404)->assertJsonPath('code', 'PR_SEASON_INVALID');
});

it('rejects inactive and cross-tenant organization units', function (): void {
    $ctx = iiiPrContext();
    $ctxB = iiiPrContext();
    $inactive = withTenant($ctx['tenant'], fn () => OrganizationUnit::factory()->inactive()->create());
    Sanctum::actingAs($ctx['creator']);

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'beneficiary_organization_unit_id' => $inactive->id,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_ORG_UNIT_INVALID');

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'beneficiary_organization_unit_id' => $ctxB['unit']->id,
    ]))->assertStatus(404)->assertJsonPath('code', 'PR_ORG_UNIT_NOT_FOUND');
});

it('enforces optional supplier eligibility and cross-tenant 404', function (): void {
    $ctx = iiiPrContext();
    $ctxB = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $ineligible = withTenant($ctx['tenant'], fn () => Supplier::factory()->create([
        'status' => SupplierStatus::Active,
    ]));
    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'optional_supplier_id' => $ineligible->id,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_SUPPLIER_NOT_ELIGIBLE');

    $foreign = withTenant($ctxB['tenant'], fn () => Supplier::factory()->create([
        'status' => SupplierStatus::Active,
    ]));
    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'optional_supplier_id' => $foreign->id,
    ]))->assertStatus(404)->assertJsonPath('code', 'PR_SUPPLIER_NOT_FOUND');

    $eligible = withTenant($ctx['tenant'], function () use ($ctx) {
        $set = approvedEligibilityConfig($ctx['tenant'], [[
            'requirement_key' => 'commercial_registration',
            'is_mandatory' => true,
        ]]);
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        supplierDocumentRecordFor($ctx['tenant'], $supplier, $def);

        return $supplier;
    });

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'optional_supplier_id' => $eligible->id,
        'title' => 'طلب مع مورد مؤهل',
    ]))->assertCreated()->assertJsonPath('data.optional_supplier_id', $eligible->id);
});

it('rejects missing urgency justification for urgent priority', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'priority' => 'urgent',
        'urgency_justification' => null,
    ]))->assertStatus(422)->assertJsonPath('code', 'PR_URGENCY_JUSTIFICATION_REQUIRED');
});

it('allows creator to record financial review when explicitly permitted', function (): void {
    $ctx = iiiPrContext();
    $creatorReviewer = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.submit',
        'purchase_requests.financial_review',
    ], $ctx['tenant']);
    Sanctum::actingAs($creatorReviewer);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    $this->postJson($ctx['base'].'/'.$id.'/submit', [])->assertOk();

    $this->postJson($ctx['base'].'/'.$id.'/financial-reviews', [
        'outcome' => 'cleared_with_conditions',
        'justification' => 'مراجعة مالية من منشئ الطلب بصلاحية صريحة',
        'version_no' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.outcome', 'cleared_with_conditions')
        ->assertJsonPath('data.reviewer_id', $creatorReviewer->id);
});

it('blocks cancel when procurement engagement lock is set', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $id = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');
    withTenant($ctx['tenant'], function () use ($id): void {
        PurchaseRequest::query()->whereKey($id)->update([
            'procurement_engaged_at' => now(),
            'procurement_engagement_ref' => 'S05-LOCK',
        ]);
    });

    $this->postJson($ctx['base'].'/'.$id.'/cancel', [
        'reason' => 'محاولة إلغاء بعد ارتباط الشراء',
    ])->assertStatus(422)->assertJsonPath('code', 'PR_PROCUREMENT_ENGAGED');
});

it('returns duplicate hints for draft payload and purchase_request_id', function (): void {
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);

    $firstId = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'title' => 'أساس التشابه',
        'lines' => [[
            'line_no' => 1,
            'description' => 'خيم قماشية',
            'quantity' => 10,
            'unit' => 'قطعة',
            'need_date' => now()->addDays(14)->toDateString(),
            'unit_estimate' => 100,
        ]],
    ]))->json('data.id');
    $this->postJson($ctx['base'].'/'.$firstId.'/submit', [])->assertOk();

    $draftId = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit'], [
        'title' => 'مسودة مشابهة',
    ]))->json('data.id');

    $byId = $this->getJson($ctx['base'].'/duplicate-hints?purchase_request_id='.$draftId);
    $byId->assertOk();
    expect($byId->json('data'))->not->toBeEmpty();

    $needDate = now()->addDays(14)->toDateString();
    $byDraft = $this->getJson($ctx['base'].'/duplicate-hints?'.http_build_query([
        'lines' => json_encode([
            ['line_no' => 1, 'description' => 'خيم قماشية', 'need_date' => $needDate],
        ], JSON_UNESCAPED_UNICODE),
    ]));
    $byDraft->assertOk();
    expect($byDraft->json('data.0.purchase_request_id'))->toBe($firstId);

    $ctxB = iiiPrContext();
    Sanctum::actingAs($ctxB['creator']);
    $this->getJson($ctx['base'].'/duplicate-hints?purchase_request_id='.$draftId)->assertNotFound();
});

it('enforces documents permissions with purchase_requests.view for PR links', function (): void {
    Storage::fake('local');
    $ctx = iiiPrContext();
    Sanctum::actingAs($ctx['creator']);
    $prId = (int) $this->postJson($ctx['base'], iiiPrPayload($ctx['unit']))->json('data.id');

    $docsOnly = createSupplierUserWith([
        'documents.view',
        'documents.upload',
        'documents.download',
    ], $ctx['tenant']);
    Sanctum::actingAs($docsOnly);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ])
        ->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('pr.txt', 'pr-doc'),
            'title' => 'مرفق طلب',
            'linkable_type' => 'purchase_request',
            'linkable_id' => $prId,
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'DOCUMENT_LINK_FORBIDDEN');

    $docsAndView = createSupplierUserWith([
        'documents.view',
        'documents.upload',
        'documents.download',
        'purchase_requests.view',
    ], $ctx['tenant']);
    Sanctum::actingAs($docsAndView);

    $created = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ])
        ->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('pr-ok.txt', 'pr-doc-ok'),
            'title' => 'مرفق مصرح',
            'linkable_type' => 'purchase_request',
            'linkable_id' => $prId,
        ])
        ->assertCreated()
        ->json('data');

    expect($created)->not->toHaveKey('storage_path');

    Sanctum::actingAs($docsOnly);
    $this->getJson('/api/v1/documents/'.$created['id'])->assertForbidden();
    $this->getJson('/api/v1/documents?linkable_type=purchase_request&linkable_id='.$prId)
        ->assertForbidden()
        ->assertJsonPath('code', 'DOCUMENT_LINK_FORBIDDEN');

    $ctxB = iiiPrContext();
    Sanctum::actingAs(createSupplierUserWith([
        'documents.view',
        'documents.upload',
        'purchase_requests.view',
    ], $ctxB['tenant']));
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ])
        ->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('xt.txt', "cross-tenant-body\n"),
            'title' => 'عبر منشأة',
            'linkable_type' => 'purchase_request',
            'linkable_id' => $prId,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_LINK_INVALID');

    // Ensure ownership metadata is tenant-scoped and non-leaky for authorized viewer.
    Sanctum::actingAs($docsAndView);
    $listed = $this->getJson('/api/v1/documents?linkable_type=purchase_request&linkable_id='.$prId)
        ->assertOk()
        ->json('data');
    expect($listed)->toHaveCount(1)
        ->and($listed[0])->not->toHaveKey('storage_path')
        ->and($listed[0])->not->toHaveKey('storage_disk');
});
