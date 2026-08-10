<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStorage;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCategory;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $fields
 * @param  array<string, string>  $headers
 */
function spaPostMultipart(string $uri, array $fields = [], array $headers = []): TestResponse
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

function makeDocumentUploadFile(string $name = 'sample.txt', string $content = 'hello-document'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function uploadDocumentViaApi(array $fields = [], ?UploadedFile $file = null): array
{
    $payload = array_merge([
        'file' => $file ?? makeDocumentUploadFile(),
        'title' => 'مستند اختبار',
    ], $fields);

    return spaPostMultipart('/api/v1/documents', $payload)
        ->assertCreated()
        ->json('data');
}

test('D01 upload generates DOC-000001 stores private path and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    Storage::fake('local');
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $data = spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile('sample.txt', 'hello-document'),
        'title' => 'عقد توريد',
        'document_number' => 'HACK',
        'tenant_id' => 999,
        'status' => 'archived',
        'storage_path' => '../etc/passwd',
    ])->assertCreated()->json('data');

    expect($data['document_number'])->toBe('DOC-000001')
        ->and($data['status'])->toBe('active')
        ->and($data['original_filename'])->toBe('sample.txt')
        ->and($data)->not->toHaveKey('storage_path')
        ->and($data)->not->toHaveKey('storage_disk')
        ->and($data)->not->toHaveKey('stored_filename');

    $document = withTenant($tenant, fn () => Document::query()->firstOrFail());
    expect($document->storage_path)->toStartWith('tenants/'.$document->tenant_id.'/documents/')
        ->and($document->stored_filename)->not->toContain('sample')
        ->and(Storage::disk($document->storage_disk)->exists($document->storage_path))->toBeTrue();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DOCUMENT_UPLOADED);
});

test('D02 numbering increments and is tenant-isolated', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();
    uploadDocumentViaApi(['title' => 'أ']);
    $second = uploadDocumentViaApi(['title' => 'ب']);
    expect($second['document_number'])->toBe('DOC-000002');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $bFirst = uploadDocumentViaApi(['title' => 'مستأجر ب']);
    expect($bFirst['document_number'])->toBe('DOC-000001');
});

test('D03 rejects oversized and invalid file types', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();
    config(['documents.max_size_bytes' => 100]);

    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile('big.txt', str_repeat('x', 120)),
        'title' => 'كبير',
    ])->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_FILE_TOO_LARGE');

    config(['documents.max_size_bytes' => 20 * 1024 * 1024]);

    $exe = UploadedFile::fake()->createWithContent('malware.exe', 'MZ');
    spaPostMultipart('/api/v1/documents', [
        'file' => $exe,
        'title' => 'تنفيذي',
    ])->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_INVALID_FILE');

    $spoof = UploadedFile::fake()->createWithContent('fake.pdf', 'not-a-pdf');
    spaPostMultipart('/api/v1/documents', [
        'file' => $spoof,
        'title' => 'تزوير',
    ])->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_INVALID_FILE');
});

test('D04 duplicate checksum allowed and category validation', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();
    $owner = auth()->user();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $active = withTenant($tenant, fn () => DocumentCategory::factory()->create(['name' => 'نشط']));
    $inactive = withTenant($tenant, fn () => DocumentCategory::factory()->inactive()->create(['name' => 'معطل']));

    $content = 'same-bytes';
    $a = uploadDocumentViaApi([
        'file' => makeDocumentUploadFile('a.txt', $content),
        'category_id' => $active->id,
    ]);
    $b = uploadDocumentViaApi([
        'file' => makeDocumentUploadFile('b.txt', $content),
        'category_id' => $active->id,
    ]);
    expect($a['checksum_sha256'])->toBe($b['checksum_sha256']);

    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile(),
        'category_id' => $inactive->id,
    ])->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_CATEGORY_INVALID');
});

test('D05 link validation and unlink', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();
    $owner = auth()->user();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $employee = withTenant($tenant, fn () => Employee::factory()->create(['organization_unit_id' => $unit->id]));
    $task = withTenant($tenant, fn () => Task::factory()->create(['created_by' => $owner->id]));

    $doc = uploadDocumentViaApi([
        'linkable_type' => 'task',
        'linkable_id' => $task->id,
    ]);
    expect($doc['link']['type'])->toBe('task')->and($doc['link']['id'])->toBe($task->id);

    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile(),
        'linkable_type' => 'task',
    ])->assertStatus(422);

    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile(),
        'linkable_type' => 'warehouse',
        'linkable_id' => 1,
    ])->assertStatus(422);

    $tenantB = Tenant::factory()->create();
    $foreignTask = withTenant($tenantB, function () use ($tenantB): Task {
        $user = tenantUser($tenantB);

        return Task::factory()->create(['created_by' => $user->id]);
    });

    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile(),
        'linkable_type' => 'task',
        'linkable_id' => $foreignTask->id,
    ])->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_LINK_INVALID');

    spaPatchJson('/api/v1/documents/'.$doc['id'], [
        'linkable_type' => null,
        'linkable_id' => null,
    ])->assertOk()->assertJsonPath('data.link', null);

    uploadDocumentViaApi([
        'linkable_type' => 'employee',
        'linkable_id' => $employee->id,
    ]);
    uploadDocumentViaApi([
        'linkable_type' => 'organization_unit',
        'linkable_id' => $unit->id,
    ]);
});

test('D06 download authorization audit missing blob and no path leakage', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    Storage::fake('local');
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $doc = uploadDocumentViaApi();
    $response = test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])
        ->get('/api/v1/documents/'.$doc['id'].'/download');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('sample.txt');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::DOCUMENT_DOWNLOADED);

    $model = withTenant($tenant, fn () => Document::query()->findOrFail($doc['id']));
    Storage::disk($model->storage_disk)->delete($model->storage_path);
    spaGetJson('/api/v1/documents/'.$doc['id'].'/download')
        ->assertNotFound()
        ->assertJsonPath('code', 'DOCUMENT_FILE_MISSING');

    $show = spaGetJson('/api/v1/documents/'.$doc['id'])->assertOk()->json('data');
    expect($show)->not->toHaveKey('storage_path');
});

test('D07 archive restore default list and delete removes blob', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    Storage::fake('local');
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $doc = uploadDocumentViaApi(['title' => 'للأرشفة']);
    $model = withTenant($tenant, fn () => Document::query()->findOrFail($doc['id']));
    $path = $model->storage_path;
    $disk = $model->storage_disk;

    spaPostJson('/api/v1/documents/'.$doc['id'].'/archive')->assertOk()
        ->assertJsonPath('data.status', 'archived');

    spaGetJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 0);
    spaGetJson('/api/v1/documents?status=archived')->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/documents?status=all')->assertOk()->assertJsonPath('meta.total', 1);

    spaPostJson('/api/v1/documents/'.$doc['id'].'/archive')
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_INVALID_STATUS_TRANSITION');

    spaPostJson('/api/v1/documents/'.$doc['id'].'/restore')->assertOk()
        ->assertJsonPath('data.status', 'active');

    spaDeleteJson('/api/v1/documents/'.$doc['id'])->assertOk();
    expect(withTenant($tenant, fn () => Document::query()->whereKey($doc['id'])->exists()))->toBeFalse()
        ->and(Storage::disk($disk)->exists($path))->toBeFalse();
});

test('D08 tenancy isolation matrix', function (): void {
    Storage::fake('local');
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant ?? Tenant::query()->findOrFail($ownerA->tenant_id);
    $doc = uploadDocumentViaApi(['title' => 'خاص أ']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson('/api/v1/documents/'.$doc['id'])->assertNotFound();
    spaGetJson('/api/v1/documents/'.$doc['id'].'/download')->assertNotFound();
    spaPatchJson('/api/v1/documents/'.$doc['id'], ['title' => 'اختراق'])->assertNotFound();
    spaPostJson('/api/v1/documents/'.$doc['id'].'/archive')->assertNotFound();
    spaDeleteJson('/api/v1/documents/'.$doc['id'])->assertNotFound();

    Sanctum::actingAs($ownerA);
    expect(withTenant($tenantA, fn () => Document::query()->whereKey($doc['id'])->exists()))->toBeTrue();
});

test('D09 rbac gates upload download delete and categories', function (): void {
    Storage::fake('local');
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);
    $doc = uploadDocumentViaApi();

    $employee = tenantUser($tenant);
    withTenant($tenant, function () use ($employee): void {
        assignRole($employee, 'employee');
    });
    Sanctum::actingAs($employee->fresh());

    spaGetJson('/api/v1/documents')->assertOk();
    spaGetJson('/api/v1/documents/'.$doc['id'].'/download')->assertOk();
    spaPostMultipart('/api/v1/documents', [
        'file' => makeDocumentUploadFile(),
    ])->assertForbidden();
    spaDeleteJson('/api/v1/documents/'.$doc['id'])->assertForbidden();
    spaPostJson('/api/v1/document-categories', [
        'name' => 'ممنوع',
    ])->assertForbidden();

    expect(PermissionCatalog::roleTemplates()['department_manager']['permissions'])
        ->toContain('documents.archive')
        ->not->toContain('documents.delete');
});

test('D10 host delete blocked while documents linked', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();
    $owner = auth()->user();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $task = withTenant($tenant, fn () => Task::factory()->create(['created_by' => $owner->id]));
    uploadDocumentViaApi([
        'linkable_type' => DocumentLinkableType::Task->value,
        'linkable_id' => $task->id,
    ]);

    spaDeleteJson('/api/v1/tasks/'.$task->id)
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_ENTITY_IN_USE');

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    uploadDocumentViaApi([
        'linkable_type' => 'organization_unit',
        'linkable_id' => $unit->id,
    ]);
    spaDeleteJson('/api/v1/organization-units/'.$unit->id)
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_ENTITY_IN_USE');

    $contract = withTenant($tenant, fn () => Contract::factory()->create(['created_by' => $owner->id]));
    uploadDocumentViaApi([
        'linkable_type' => 'contract',
        'linkable_id' => $contract->id,
    ]);
    spaDeleteJson('/api/v1/contracts/'.$contract->id)
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_ENTITY_IN_USE');
});

test('D11 list filters search and category lifecycle', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();

    $cat = spaPostJson('/api/v1/document-categories', [
        'name' => 'عقود',
        'description' => 'تصنيف',
    ])->assertCreated()->json('data');

    uploadDocumentViaApi([
        'title' => 'بحث خاص',
        'category_id' => $cat['id'],
    ]);
    uploadDocumentViaApi(['title' => 'آخر']);

    spaGetJson('/api/v1/documents?search=بحث')->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/documents?category_id='.$cat['id'])->assertOk()->assertJsonPath('meta.total', 1);

    spaDeleteJson('/api/v1/document-categories/'.$cat['id'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'DOCUMENT_CATEGORY_IN_USE');

    spaPatchJson('/api/v1/document-categories/'.$cat['id'], [
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);
});

test('D12 storage path uses TenantStorage DOCUMENTS namespace', function (): void {
    Storage::fake('local');
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);
    $doc = uploadDocumentViaApi();
    $model = withTenant($tenant, fn () => Document::query()->findOrFail($doc['id']));
    $expectedPrefix = withTenant($tenant, fn () => app(TenantStorage::class)->path(TenantStorage::DOCUMENTS));

    expect($model->storage_path)->toStartWith($expectedPrefix.'/')
        ->and($model->original_filename)->not->toBe($model->stored_filename);
});
