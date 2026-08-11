<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\Contracts\Models\ContractStatusTransition;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function contractActorTenant(): Tenant
{
    $user = auth()->user();
    expect($user)->not->toBeNull();

    return $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);
}

function createContractCategory(array $attrs = [], ?Tenant $tenant = null): ContractCategory
{
    $tenant ??= contractActorTenant();

    return withTenant($tenant, fn () => ContractCategory::factory()->create($attrs));
}

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function createContractViaApi(array $attrs = []): array
{
    $categoryId = $attrs['contract_category_id'] ?? createContractCategory()->id;
    unset($attrs['contract_category_id']);

    return spaPostJson('/api/v1/contracts', array_merge([
        'title' => 'عقد اختبار',
        'contract_category_id' => $categoryId,
        'counterparty_name' => 'طرف مقابل',
        'start_date' => now()->toDateString(),
    ], $attrs))->assertCreated()->json('data');
}

/**
 * Walk draft → in_review → approved → signed → executing as needed.
 *
 * @return array<string, mixed>
 */
function advanceContractTo(int|string $contractId, string $status): array
{
    $path = [
        ContractStatus::InReview->value => 'submit-review',
        ContractStatus::Approved->value => 'approve',
        ContractStatus::Signed->value => 'sign',
        ContractStatus::Executing->value => 'execute',
    ];

    $order = [
        ContractStatus::Draft->value,
        ContractStatus::InReview->value,
        ContractStatus::Approved->value,
        ContractStatus::Signed->value,
        ContractStatus::Executing->value,
    ];

    $targetIndex = array_search($status, $order, true);
    expect($targetIndex)->not->toBeFalse();

    $current = spaGetJson("/api/v1/contracts/{$contractId}")->assertOk()->json('data');
    $currentIndex = array_search($current['status'], $order, true);
    expect($currentIndex)->not->toBeFalse();

    for ($i = $currentIndex + 1; $i <= $targetIndex; $i++) {
        $step = $order[$i];
        $endpoint = $path[$step];
        $current = spaPostJson("/api/v1/contracts/{$contractId}/{$endpoint}", [])
            ->assertOk()
            ->assertJsonPath('data.status', $step)
            ->json('data');
    }

    return $current;
}

test('C01 create generates CTR-000001 ignores client fields and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $category = createContractCategory(['name' => 'توريد', 'code' => 'SUP']);

    $data = spaPostJson('/api/v1/contracts', [
        'title' => 'عقد إنشاء',
        'contract_category_id' => $category->id,
        'counterparty_name' => 'مورد أ',
        'start_date' => '2026-01-01',
        'contract_number' => 'HACK-9',
        'status' => 'executing',
        'tenant_id' => 999999,
    ])->assertCreated()
        ->assertJsonPath('data.contract_number', 'CTR-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'عقد إنشاء')
        ->json('data');

    $shown = spaGetJson("/api/v1/contracts/{$data['id']}")->assertOk()->json('data');
    expect($shown['transitions'])->toHaveCount(1)
        ->and($shown['transitions'][0]['from_status'])->toBeNull()
        ->and($shown['transitions'][0]['to_status'])->toBe('draft');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTRACT_CREATED,
    );
});

test('C02 sequential numbers and per-tenant independent sequences', function (): void {
    actingAsTenantOwner();
    $category = createContractCategory();

    $numbers = [];
    for ($i = 0; $i < 5; $i++) {
        $numbers[] = spaPostJson('/api/v1/contracts', [
            'title' => "عقد {$i}",
            'contract_category_id' => $category->id,
            'counterparty_name' => "طرف {$i}",
            'start_date' => '2026-01-01',
        ])->assertCreated()->json('data.contract_number');
    }

    expect($numbers)->toBe(['CTR-000001', 'CTR-000002', 'CTR-000003', 'CTR-000004', 'CTR-000005']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $categoryB = createContractCategory([], $tenantB);

    spaPostJson('/api/v1/contracts', [
        'title' => 'عقد B',
        'contract_category_id' => $categoryB->id,
        'counterparty_name' => 'طرف B',
        'start_date' => '2026-01-01',
    ])->assertCreated()->assertJsonPath('data.contract_number', 'CTR-000001');

    expect(
        Contract::withoutGlobalScopes()->where('contract_number', 'CTR-000001')->count()
    )->toBe(2);
});

test('C03 inactive and foreign category rejected', function (): void {
    $ownerA = actingAsTenantOwner();
    $inactive = createContractCategory(['is_active' => false, 'name' => 'معطل', 'code' => 'OFF']);

    spaPostJson('/api/v1/contracts', [
        'title' => 'فئة معطلة',
        'contract_category_id' => $inactive->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertStatus(422)->assertJsonPath('code', 'CONTRACT_CATEGORY_INVALID');

    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);
    $foreign = createContractCategory(['name' => 'أجنبي', 'code' => 'FOR'], $tenantB);

    Sanctum::actingAs($ownerA);
    spaPostJson('/api/v1/contracts', [
        'title' => 'فئة أجنبية',
        'contract_category_id' => $foreign->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertStatus(422);
});

test('C04 invalid date range rejected; open-ended and currency default OK', function (): void {
    actingAsTenantOwner();
    $category = createContractCategory();

    $invalid = spaPostJson('/api/v1/contracts', [
        'title' => 'نطاق خاطئ',
        'contract_category_id' => $category->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-06-01',
        'end_date' => '2026-01-01',
    ])->assertStatus(422);

    expect(in_array($invalid->json('code'), ['CONTRACT_INVALID_DATE_RANGE', 'VALIDATION_ERROR'], true))->toBeTrue();

    spaPostJson('/api/v1/contracts', [
        'title' => 'مفتوح',
        'contract_category_id' => $category->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
        'end_date' => null,
    ])->assertCreated()
        ->assertJsonPath('data.end_date', null)
        ->assertJsonPath('data.currency', 'SAR');
});

test('C05 draft PATCH ok; non-draft PATCH not editable', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $contract = createContractViaApi(['title' => 'قابل للتعديل']);

    spaPatchJson("/api/v1/contracts/{$contract['id']}", [
        'title' => 'محدّث',
        'notes' => 'ملاحظة',
    ])->assertOk()
        ->assertJsonPath('data.title', 'محدّث')
        ->assertJsonPath('data.notes', 'ملاحظة');

    advanceContractTo($contract['id'], ContractStatus::InReview->value);

    spaPatchJson("/api/v1/contracts/{$contract['id']}", [
        'title' => 'ممنوع',
    ])->assertStatus(422)->assertJsonPath('code', 'CONTRACT_NOT_EDITABLE');
});

test('C06 lifecycle happy path to executing with history and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $contract = createContractViaApi(['title' => 'مسار كامل']);

    advanceContractTo($contract['id'], ContractStatus::Executing->value);

    $shown = spaGetJson("/api/v1/contracts/{$contract['id']}")->assertOk()->json('data');
    expect($shown['status'])->toBe('executing')
        ->and($shown['transitions'])->toHaveCount(5);

    $statuses = array_column($shown['transitions'], 'to_status');
    expect($statuses)->toBe(['draft', 'in_review', 'approved', 'signed', 'executing']);

    foreach ([
        AuthorizationSecurityEvent::CONTRACT_CREATED,
        AuthorizationSecurityEvent::CONTRACT_SUBMITTED_REVIEW,
        AuthorizationSecurityEvent::CONTRACT_APPROVED,
        AuthorizationSecurityEvent::CONTRACT_SIGNED,
        AuthorizationSecurityEvent::CONTRACT_EXECUTED,
    ] as $event) {
        Event::assertDispatched(
            AuthorizationSecurityEvent::class,
            fn (AuthorizationSecurityEvent $e): bool => $e->name === $event,
        );
    }
});

test('C07 invalid transition rejected', function (): void {
    actingAsTenantOwner();
    $contract = createContractViaApi();

    spaPostJson("/api/v1/contracts/{$contract['id']}/approve")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_INVALID_STATUS_TRANSITION');

    spaPostJson("/api/v1/contracts/{$contract['id']}/execute")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_INVALID_STATUS_TRANSITION');
});

test('C08 return and cancel require comment; cancel from signed rejected', function (): void {
    actingAsTenantOwner();
    $contract = createContractViaApi();
    advanceContractTo($contract['id'], ContractStatus::InReview->value);

    spaPostJson("/api/v1/contracts/{$contract['id']}/return-draft")
        ->assertStatus(422);

    spaPostJson("/api/v1/contracts/{$contract['id']}/return-draft", [
        'comment' => 'يحتاج تعديل',
    ])->assertOk()->assertJsonPath('data.status', 'draft');

    advanceContractTo($contract['id'], ContractStatus::Approved->value);

    spaPostJson("/api/v1/contracts/{$contract['id']}/cancel")
        ->assertStatus(422);

    spaPostJson("/api/v1/contracts/{$contract['id']}/cancel", [
        'comment' => 'ألغي قبل التوقيع',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    $signed = createContractViaApi(['title' => 'موقع']);
    advanceContractTo($signed['id'], ContractStatus::Signed->value);

    spaPostJson("/api/v1/contracts/{$signed['id']}/cancel", [
        'comment' => 'محاولة إلغاء',
    ])->assertStatus(422)->assertJsonPath('code', 'CONTRACT_INVALID_STATUS_TRANSITION');
});

test('C09 sign from approved records manual attestation', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $contract = createContractViaApi(['title' => 'توقيع']);
    advanceContractTo($contract['id'], ContractStatus::Approved->value);

    spaPostJson("/api/v1/contracts/{$contract['id']}/sign", [
        'comment' => 'تم التوقيع خارج النظام',
    ])->assertOk()->assertJsonPath('data.status', 'signed');

    $shown = spaGetJson("/api/v1/contracts/{$contract['id']}")->assertOk()->json('data');
    $signRow = collect($shown['transitions'])->firstWhere('to_status', 'signed');
    expect($signRow)->not->toBeNull()
        ->and($signRow['actor'])->not->toBeNull()
        ->and($signRow['comment'])->toBe('تم التوقيع خارج النظام')
        ->and($shown)->not->toHaveKey('signature');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTRACT_SIGNED,
    );
});

test('C10 renew from executing; second renew blocked; transitions not cloned', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $contract = createContractViaApi([
        'title' => 'للتجديد',
        'end_date' => now()->addYear()->toDateString(),
        'value' => 1000,
        'notes' => 'نسخ',
    ]);
    advanceContractTo($contract['id'], ContractStatus::Executing->value);

    $sourceBefore = spaGetJson("/api/v1/contracts/{$contract['id']}")->assertOk()->json('data');
    $sourceTransitionCount = count($sourceBefore['transitions']);

    $result = spaPostJson("/api/v1/contracts/{$contract['id']}/renew")
        ->assertOk()
        ->json('data');

    expect($result['source']['status'])->toBe('renewed')
        ->and($result['successor']['status'])->toBe('draft')
        ->and($result['successor']['contract_number'])->toBe('CTR-000002')
        ->and($result['successor']['renewed_from_contract_id'])->toBe($contract['id'])
        ->and($result['successor']['title'])->toBe('للتجديد')
        ->and($result['successor']['notes'])->toBe('نسخ')
        ->and($result['successor']['end_date'])->toBeNull();

    $successor = spaGetJson("/api/v1/contracts/{$result['successor']['id']}")->assertOk()->json('data');
    expect($successor['transitions'])->toHaveCount(1)
        ->and($successor['transitions'][0]['from_status'])->toBeNull()
        ->and($successor['transitions'][0]['to_status'])->toBe('draft')
        ->and(count($successor['transitions']))->not->toBe($sourceTransitionCount);

    spaPostJson("/api/v1/contracts/{$contract['id']}/renew")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_ALREADY_RENEWED');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTRACT_RENEWED,
    );
});

test('C11 expire via artisan is idempotent with null actor', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? contractActorTenant();

    $contract = withTenant($tenant, function () use ($owner) {
        return Contract::factory()->expiredEndDate()->create([
            'created_by' => $owner->id,
        ]);
    });

    Artisan::call('contracts:expire');
    expect(Artisan::output())->toContain('Expired 1');

    $fresh = withTenant($tenant, fn () => Contract::query()->findOrFail($contract->id));
    expect($fresh->status)->toBe(ContractStatus::Expired);

    $transition = withTenant($tenant, fn () => ContractStatusTransition::query()
        ->where('contract_id', $contract->id)
        ->where('to_status', ContractStatus::Expired->value)
        ->first());

    expect($transition)->not->toBeNull()
        ->and($transition->actor_user_id)->toBeNull();

    Artisan::call('contracts:expire');
    expect(Artisan::output())->toContain('Expired 0');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTRACT_EXPIRED,
    );
});

test('C12 delete draft OK; after leaving draft and non-draft forbidden', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $draft = createContractViaApi(['title' => 'للحذف']);
    spaDeleteJson("/api/v1/contracts/{$draft['id']}")->assertOk();
    spaGetJson("/api/v1/contracts/{$draft['id']}")->assertNotFound();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTRACT_DELETED,
    );

    $returned = createContractViaApi(['title' => 'عاد للمسودة']);
    advanceContractTo($returned['id'], ContractStatus::InReview->value);
    spaPostJson("/api/v1/contracts/{$returned['id']}/return-draft", [
        'comment' => 'راجع',
    ])->assertOk();

    spaDeleteJson("/api/v1/contracts/{$returned['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_DELETE_FORBIDDEN');

    $reviewing = createContractViaApi(['title' => 'قيد المراجعة']);
    advanceContractTo($reviewing['id'], ContractStatus::InReview->value);
    spaDeleteJson("/api/v1/contracts/{$reviewing['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_DELETE_FORBIDDEN');
});

test('C13 categories CRUD activate deactivate delete rules', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $category = spaPostJson('/api/v1/contract-categories', [
        'name' => 'توريد',
        'code' => 'SUPPLY',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'SUPPLY')
        ->assertJsonPath('data.is_active', true)
        ->json('data');

    spaPatchJson("/api/v1/contract-categories/{$category['id']}", [
        'name' => 'توريد محدّث',
    ])->assertOk()->assertJsonPath('data.name', 'توريد محدّث');

    spaPostJson("/api/v1/contract-categories/{$category['id']}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    spaPostJson('/api/v1/contracts', [
        'title' => 'فئة معطلة',
        'contract_category_id' => $category['id'],
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertStatus(422)->assertJsonPath('code', 'CONTRACT_CATEGORY_INVALID');

    spaPostJson("/api/v1/contract-categories/{$category['id']}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $unused = spaPostJson('/api/v1/contract-categories', [
        'name' => 'غير مستخدم',
        'code' => 'UNUSED',
    ])->assertCreated()->json('data');

    spaDeleteJson("/api/v1/contract-categories/{$unused['id']}")->assertOk();

    createContractViaApi(['contract_category_id' => $category['id']]);

    spaDeleteJson("/api/v1/contract-categories/{$category['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CONTRACT_CATEGORY_IN_USE');
});

test('C14 cross-tenant isolation for contracts and foreign refs', function (): void {
    $ownerA = actingAsTenantOwner();
    $contractA = createContractViaApi(['title' => 'عقد A']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $categoryB = createContractCategory(['name' => 'B', 'code' => 'B1'], $tenantB);
    $contractB = createContractViaApi([
        'title' => 'عقد B',
        'contract_category_id' => $categoryB->id,
    ]);
    $employeeB = withTenant($tenantB, fn () => Employee::factory()->create());
    $unitB = withTenant($tenantB, fn () => OrganizationUnit::factory()->create());

    Sanctum::actingAs($ownerA);

    spaGetJson("/api/v1/contracts/{$contractB['id']}")->assertNotFound();
    spaPatchJson("/api/v1/contracts/{$contractB['id']}", ['title' => 'hack'])->assertNotFound();
    spaPostJson("/api/v1/contracts/{$contractB['id']}/submit-review")->assertNotFound();
    spaPostJson("/api/v1/contracts/{$contractB['id']}/renew")->assertNotFound();
    spaDeleteJson("/api/v1/contracts/{$contractB['id']}")->assertNotFound();

    spaGetJson('/api/v1/contracts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $contractA['id']);

    $categoryA = createContractCategory(['name' => 'محلي', 'code' => 'LOC']);

    spaPostJson('/api/v1/contracts', [
        'title' => 'فئة أجنبية',
        'contract_category_id' => $categoryB->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertStatus(422);

    spaPostJson('/api/v1/contracts', [
        'title' => 'موظف أجنبي',
        'contract_category_id' => $categoryA->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
        'employee_id' => $employeeB->id,
    ])->assertStatus(422);

    spaPostJson('/api/v1/contracts', [
        'title' => 'وحدة أجنبية',
        'contract_category_id' => $categoryA->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
        'organization_unit_id' => $unitB->id,
    ])->assertStatus(422);
});

test('C15 RBAC supervisor forbidden create; auditor view only', function (): void {
    spaGetJson('/api/v1/contracts')->assertUnauthorized();

    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));

    $supervisor = tenantUser($tenant);
    assignRole($supervisor, 'supervisor');
    Sanctum::actingAs($supervisor);

    $category = withTenant($tenant, fn () => ContractCategory::factory()->create());

    spaPostJson('/api/v1/contracts', [
        'title' => 'ممنوع',
        'contract_category_id' => $category->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertForbidden();

    $owner = actingAsTenantOwner($tenant);
    $contract = createContractViaApi(['contract_category_id' => $category->id]);

    $auditor = tenantUser($tenant);
    assignRole($auditor, 'auditor');
    Sanctum::actingAs($auditor);
    $auditor->unsetRelation('roles');
    app(EffectivePermissions::class)->forgetUser($auditor);

    spaGetJson('/api/v1/contracts')->assertOk();
    spaGetJson("/api/v1/contracts/{$contract['id']}")->assertOk();
    spaPostJson('/api/v1/contracts', [
        'title' => 'مدقق لا ينشئ',
        'contract_category_id' => $category->id,
        'counterparty_name' => 'طرف',
        'start_date' => '2026-01-01',
    ])->assertForbidden();
});

test('C16 list filters status expiring_soon and search', function (): void {
    actingAsTenantOwner();
    $category = createContractCategory();

    $draft = createContractViaApi([
        'title' => 'عقد بحث خاص',
        'counterparty_name' => 'شركة البحث',
        'contract_category_id' => $category->id,
    ]);

    $expiring = createContractViaApi([
        'title' => 'عقد ينتهي قريبا',
        'contract_category_id' => $category->id,
        'end_date' => now()->addDays(10)->toDateString(),
    ]);
    advanceContractTo($expiring['id'], ContractStatus::Executing->value);

    $far = createContractViaApi([
        'title' => 'عقد بعيد',
        'contract_category_id' => $category->id,
        'end_date' => now()->addDays(90)->toDateString(),
    ]);
    advanceContractTo($far['id'], ContractStatus::Executing->value);

    spaGetJson('/api/v1/contracts?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $draft['id']);

    spaGetJson('/api/v1/contracts?search=بحث خاص')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $draft['id']);

    spaGetJson('/api/v1/contracts?search=شركة البحث')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    spaGetJson('/api/v1/contracts?expiring_soon=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expiring['id']);
});
