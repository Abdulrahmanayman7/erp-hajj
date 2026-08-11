<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createAssetViaApi(array $overrides = []): array
{
    return spaPostJson('/api/v1/assets', array_merge([
        'name' => 'أصل اختبار',
    ], $overrides))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $attrs
 */
function createAssetEmployee(array $attrs = []): Employee
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, function () use ($attrs): Employee {
        if (! isset($attrs['organization_unit_id'])) {
            $attrs['organization_unit_id'] = OrganizationUnit::factory()->create()->id;
        }

        return Employee::factory()->create($attrs);
    });
}

/**
 * @param  array<string, mixed>  $fields
 * @param  array<string, string>  $headers
 */
function assetSpaPostMultipart(string $uri, array $fields = [], array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ], $headers))
        ->post($uri, $fields);
}

// ─── NUMBERING ───────────────────────────────────────────────────────────────

test('A01 asset create → AST-000001, ignore client asset_number/status/tenant_id', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $data = spaPostJson('/api/v1/assets', [
        'name' => 'جهاز محمول',
        'asset_number' => 'HACK-1',
        'status' => 'retired',
        'tenant_id' => 999,
        'current_custody_id' => 1,
    ])->assertCreated()->json('data');

    expect($data['asset_number'])->toBe('AST-000001')
        ->and($data['status'])->toBe('available')
        ->and($data['current_custody'] ?? null)->toBeNull();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn ($e) => $e->name === AuthorizationSecurityEvent::ASSET_CREATED);
});

test('A02 custody assign → CUS-000001 and asset in_use', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $employee = createAssetEmployee();

    $data = spaPostJson("/api/v1/assets/{$asset['id']}/assign", [
        'employee_id' => $employee->id,
        'condition_at_assignment' => 'good',
        'assignment_notes' => 'تسليم تجريبي',
    ])->assertOk()->json('data');

    expect($data['status'])->toBe('in_use')
        ->and($data['current_custody']['custody_number'])->toBe('CUS-000001')
        ->and($data['current_custody']['employee']['id'])->toBe($employee->id);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn ($e) => $e->name === AuthorizationSecurityEvent::ASSET_ASSIGNED);
});

test('A03 numbering is tenant-isolated', function (): void {
    actingAsTenantOwner();
    createAssetViaApi(['name' => 'أصل أ']);
    expect(spaGetJson('/api/v1/assets')->json('data.0.asset_number'))->toBe('AST-000001');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    $data = createAssetViaApi(['name' => 'أصل ب']);
    expect($data['asset_number'])->toBe('AST-000001');
});

// ─── CATEGORY ────────────────────────────────────────────────────────────────

test('A04 category CRUD and in-use delete blocked', function (): void {
    actingAsTenantOwner();

    $cat = spaPostJson('/api/v1/asset-categories', [
        'name' => 'أجهزة',
        'description' => 'تصنيف',
    ])->assertCreated()->json('data');

    spaPatchJson("/api/v1/asset-categories/{$cat['id']}", [
        'name' => 'أجهزة مكتبية',
    ])->assertOk();

    createAssetViaApi(['category_id' => $cat['id']]);

    spaDeleteJson("/api/v1/asset-categories/{$cat['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ASSET_CATEGORY_IN_USE');
});

// ─── ASSET CRUD ──────────────────────────────────────────────────────────────

test('A05 serial uniqueness and update ignores status', function (): void {
    actingAsTenantOwner();
    createAssetViaApi(['serial_number' => 'SN-1']);

    spaPostJson('/api/v1/assets', [
        'name' => 'مكرر',
        'serial_number' => 'SN-1',
    ])->assertStatus(422)->assertJsonPath('code', 'ASSET_SERIAL_ALREADY_EXISTS');

    $asset = createAssetViaApi(['name' => 'قابل للتحديث', 'barcode' => 'BC-1']);
    $updated = spaPatchJson("/api/v1/assets/{$asset['id']}", [
        'name' => 'اسم جديد',
        'status' => 'lost',
        'asset_number' => 'HACK',
    ])->assertOk()->json('data');

    expect($updated['name'])->toBe('اسم جديد')
        ->and($updated['status'])->toBe('available')
        ->and($updated['asset_number'])->toBe($asset['asset_number']);
});

test('A06 delete unused OK; custody history blocks delete', function (): void {
    actingAsTenantOwner();
    $unused = createAssetViaApi(['name' => 'قابل للحذف']);
    spaDeleteJson("/api/v1/assets/{$unused['id']}")->assertOk();

    $asset = createAssetViaApi(['name' => 'بعهدة']);
    $employee = createAssetEmployee();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();
    spaPostJson("/api/v1/assets/{$asset['id']}/return", [
        'next_status' => 'available',
    ])->assertOk();

    spaDeleteJson("/api/v1/assets/{$asset['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ASSET_IN_USE');
});

// ─── LIFECYCLE ───────────────────────────────────────────────────────────────

test('A07 maintenance restore retire lost transitions', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $asset = createAssetViaApi();
    spaPostJson("/api/v1/assets/{$asset['id']}/maintenance")->assertOk()
        ->assertJsonPath('data.status', 'maintenance');
    spaPostJson("/api/v1/assets/{$asset['id']}/restore")->assertOk()
        ->assertJsonPath('data.status', 'available');

    spaPostJson("/api/v1/assets/{$asset['id']}/retire", ['reason' => 'نهاية العمر'])
        ->assertOk()
        ->assertJsonPath('data.status', 'retired');

    spaPostJson("/api/v1/assets/{$asset['id']}/assign", [
        'employee_id' => createAssetEmployee()->id,
    ])->assertStatus(422)->assertJsonPath('code', 'ASSET_NOT_AVAILABLE');

    $lost = createAssetViaApi(['name' => 'مفقود']);
    spaPostJson("/api/v1/assets/{$lost['id']}/declare-lost", ['reason' => 'فقد أثناء النقل'])
        ->assertOk()
        ->assertJsonPath('data.status', 'lost');
});

test('A08 retire/lost require reason', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();

    spaPostJson("/api/v1/assets/{$asset['id']}/retire", ['reason' => ''])
        ->assertStatus(422);
    spaPostJson("/api/v1/assets/{$asset['id']}/declare-lost", [])
        ->assertStatus(422);
});

// ─── CUSTODY ASSIGN / RETURN ─────────────────────────────────────────────────

test('A09 assign requires available and active employee', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $inactive = createAssetEmployee();
    withTenant($inactive->tenant ?? Tenant::query()->findOrFail(auth()->user()->tenant_id), function () use ($inactive): void {
        $inactive->update(['status' => 'inactive']);
    });

    spaPostJson("/api/v1/assets/{$asset['id']}/assign", [
        'employee_id' => $inactive->id,
    ])->assertStatus(422)->assertJsonPath('code', 'ASSET_EMPLOYEE_INVALID');

    spaPostJson("/api/v1/assets/{$asset['id']}/maintenance")->assertOk();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", [
        'employee_id' => createAssetEmployee()->id,
    ])->assertStatus(422)->assertJsonPath('code', 'ASSET_NOT_AVAILABLE');
});

test('A10 return closes custody and applies next_status', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $employee = createAssetEmployee();

    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();

    $returned = spaPostJson("/api/v1/assets/{$asset['id']}/return", [
        'next_status' => 'maintenance',
        'condition_at_return' => 'fair',
        'return_notes' => 'يحتاج صيانة',
    ])->assertOk()->json('data');

    expect($returned['status'])->toBe('maintenance')
        ->and($returned['current_custody'])->toBeNull();

    $custody = spaGetJson('/api/v1/asset-custodies?status=returned')->assertOk()->json('data.0');
    expect($custody['status'])->toBe('returned')
        ->and($custody['is_overdue'])->toBeFalse();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn ($e) => $e->name === AuthorizationSecurityEvent::ASSET_RETURNED);
});

test('A11 return without custody and invalid next_status', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();

    spaPostJson("/api/v1/assets/{$asset['id']}/return", [
        'next_status' => 'available',
    ])->assertStatus(422)->assertJsonPath('code', 'ASSET_NOT_ASSIGNED');

    $employee = createAssetEmployee();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();

    spaPostJson("/api/v1/assets/{$asset['id']}/return", [
        'next_status' => 'lost',
    ])->assertStatus(422);
});

test('A12 sequential double-assign: only one active custody', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $e1 = createAssetEmployee();
    $e2 = createAssetEmployee();

    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $e1->id])->assertOk();

    $second = spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $e2->id])
        ->assertStatus(422);
    expect(in_array($second->json('code'), ['ASSET_ALREADY_ASSIGNED', 'ASSET_NOT_AVAILABLE', 'ASSET_CUSTODY_CONFLICT'], true))->toBeTrue();

    $tenant = auth()->user()->tenant ?? Tenant::query()->findOrFail(auth()->user()->tenant_id);
    withTenant($tenant, function () use ($asset): void {
        $active = AssetCustody::query()->where('asset_id', $asset['id'])->where('status', CustodyStatus::Active->value)->count();
        expect($active)->toBe(1);

        $row = Asset::query()->findOrFail($asset['id']);
        expect($row->status)->toBe(AssetStatus::InUse)
            ->and($row->current_custody_id)->not->toBeNull();
    });
});

test('A13 declare-lost while in_use closes custody', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $employee = createAssetEmployee();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();

    $data = spaPostJson("/api/v1/assets/{$asset['id']}/declare-lost", [
        'reason' => 'فقد أثناء العهدة',
    ])->assertOk()->json('data');

    expect($data['status'])->toBe('lost')
        ->and($data['current_custody'])->toBeNull();

    $tenant = auth()->user()->tenant ?? Tenant::query()->findOrFail(auth()->user()->tenant_id);
    withTenant($tenant, function () use ($asset): void {
        expect(
            AssetCustody::query()->where('asset_id', $asset['id'])->where('status', CustodyStatus::Active->value)->count()
        )->toBe(0);
    });
});

// ─── TENANCY ─────────────────────────────────────────────────────────────────

test('A14 cross-tenant asset/custody show → 404', function (): void {
    actingAsTenantOwner();
    $asset = createAssetViaApi();
    $employee = createAssetEmployee();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();
    $custodyId = spaGetJson("/api/v1/assets/{$asset['id']}")->json('data.current_custody.id');

    $tenantB = Tenant::factory()->create();
    Sanctum::actingAs(provisionTenantRbac($tenantB));

    spaGetJson("/api/v1/assets/{$asset['id']}")->assertNotFound();
    spaGetJson("/api/v1/asset-custodies/{$custodyId}")->assertNotFound();
});

// ─── RBAC / SELF-VIEW ────────────────────────────────────────────────────────

test('A15 department manager cannot delete or retire', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    $mgr = tenantUser($tenant);
    assignRole($mgr, 'department_manager');
    Sanctum::actingAs($owner);
    $asset = createAssetViaApi();

    Sanctum::actingAs($mgr);
    spaDeleteJson("/api/v1/assets/{$asset['id']}")->assertForbidden();
    spaPostJson("/api/v1/assets/{$asset['id']}/retire", ['reason' => 'x'])->assertForbidden();
});

test('A16 holder self-view read-only; my-custodies scoped', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    Sanctum::actingAs($owner);

    $holderUser = tenantUser($tenant);
    assignRole($holderUser, 'employee');

    $employee = withTenant($tenant, function () use ($holderUser): Employee {
        return Employee::factory()->create([
            'organization_unit_id' => OrganizationUnit::factory()->create()->id,
            'user_id' => $holderUser->id,
        ]);
    });
    $other = createAssetEmployee();

    $mine = createAssetViaApi(['name' => 'عهدي']);
    $theirs = createAssetViaApi(['name' => 'عهدة غيري']);
    spaPostJson("/api/v1/assets/{$mine['id']}/assign", ['employee_id' => $employee->id])->assertOk();
    spaPostJson("/api/v1/assets/{$theirs['id']}/assign", ['employee_id' => $other->id])->assertOk();

    Sanctum::actingAs($holderUser);

    spaGetJson("/api/v1/assets/{$mine['id']}")->assertOk();
    spaPostJson("/api/v1/assets/{$mine['id']}/return", [
        'next_status' => 'available',
    ])->assertForbidden();

    $mineList = spaGetJson('/api/v1/my-custodies')->assertOk()->json('data');
    expect(collect($mineList)->pluck('employee.id')->unique()->all())->toBe([$employee->id]);
});

test('A17 user without employee gets empty my-custodies', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    $user = tenantUser($tenant);
    assignRole($user, 'employee');
    Sanctum::actingAs($user);

    expect(spaGetJson('/api/v1/my-custodies')->assertOk()->json('data'))->toBe([]);
});

// ─── FILTERS / OVERDUE ───────────────────────────────────────────────────────

test('A18 filters assets by employee and custodies overdue', function (): void {
    actingAsTenantOwner();
    $employee = createAssetEmployee();
    $asset = createAssetViaApi(['name' => 'مرشح']);
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", [
        'employee_id' => $employee->id,
        'expected_return_at' => now()->subDay()->toIso8601String(),
    ])->assertOk();

    $list = spaGetJson('/api/v1/assets?employee_id='.$employee->id)->assertOk()->json('data');
    expect(collect($list)->pluck('id'))->toContain($asset['id']);

    $overdue = spaGetJson('/api/v1/asset-custodies?overdue=1')->assertOk()->json('data');
    expect($overdue)->not->toBeEmpty()
        ->and($overdue[0]['is_overdue'])->toBeTrue();
});

// ─── DOCUMENTS + INVENTORY BOUNDARY ──────────────────────────────────────────

test('A19 documents morph and delete guard; no inventory side effects', function (): void {
    actingAsTenantOwner();
    $tenant = auth()->user()->tenant ?? Tenant::query()->findOrFail(auth()->user()->tenant_id);

    $before = withTenant($tenant, fn () => [
        'movements' => InventoryMovement::query()->count(),
        'balances' => InventoryBalance::query()->count(),
    ]);

    $asset = createAssetViaApi();
    $employee = createAssetEmployee();
    spaPostJson("/api/v1/assets/{$asset['id']}/assign", ['employee_id' => $employee->id])->assertOk();
    spaPostJson("/api/v1/assets/{$asset['id']}/return", ['next_status' => 'available'])->assertOk();

    withTenant($tenant, function () use ($before): void {
        expect(InventoryMovement::query()->count())->toBe($before['movements'])
            ->and(InventoryBalance::query()->count())->toBe($before['balances']);
    });

    $fresh = createAssetViaApi(['name' => 'مع مستند']);
    assetSpaPostMultipart('/api/v1/documents', [
        'file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'),
        'title' => 'مستند أصل',
        'linkable_type' => 'asset',
        'linkable_id' => $fresh['id'],
    ])->assertCreated();

    spaDeleteJson("/api/v1/assets/{$fresh['id']}")->assertStatus(422);
});

test('A20 unauthenticated assets → 401', function (): void {
    spaGetJson('/api/v1/assets')->assertUnauthorized();
});
