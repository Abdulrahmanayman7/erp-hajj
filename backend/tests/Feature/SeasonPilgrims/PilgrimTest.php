<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Sanctum\Sanctum;

it('creates a provisional pilgrim without identity documents', function (): void {
    actingAsTenantOwner();

    $pilgrim = spaPostJson('/api/v1/pilgrims', ['full_name' => 'حاج مؤقت'])
        ->assertCreated()
        ->json('data');

    expect($pilgrim['full_name'])->toBe('حاج مؤقت')
        ->and($pilgrim['gender'])->toBeNull();

    spaGetJson("/api/v1/pilgrims/{$pilgrim['id']}")
        ->assertOk()
        ->assertJsonPath('data.has_identity_documents', false)
        ->assertJsonCount(0, 'data.identity_documents');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_CREATED)->exists())->toBeTrue();
});

it('validates pilgrim payloads and permissions', function (): void {
    $owner = actingAsTenantOwner();

    spaPostJson('/api/v1/pilgrims', [])->assertStatus(422)->assertJsonValidationErrors(['full_name']);
    spaPostJson('/api/v1/pilgrims', ['full_name' => 'x', 'gender' => 'other'])
        ->assertStatus(422)->assertJsonValidationErrors(['gender']);

    Sanctum::actingAs(tenantUser($owner->tenant));
    spaGetJson('/api/v1/pilgrims')->assertForbidden();
});

it('adds an identity document and audits it with the number masked', function (): void {
    actingAsTenantOwner();
    $pilgrim = spCreatePilgrim();

    $doc = spAddDocument($pilgrim['id'], 'AB-123 4567');

    expect($doc['document_type'])->toBe('passport')
        ->and($doc['issuing_country'])->toBe('SA');

    $stored = PilgrimIdentityDocument::query()->withoutGlobalScopes()->find($doc['id']);
    expect($stored->normalized_number)->toBe('AB1234567');

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_ADDED)->first();
    expect($row)->not->toBeNull();
    expect(json_encode([$row->after_values, $row->metadata], JSON_UNESCAPED_UNICODE))->not->toContain('AB-123 4567')
        ->not->toContain('AB1234567');
});

it('rejects duplicate identity documents within a tenant using normalized numbers', function (): void {
    actingAsTenantOwner();
    $first = spCreatePilgrim();
    $second = spCreatePilgrim(['full_name' => 'حاج آخر']);
    spAddDocument($first['id'], 'X1234567');

    spaPostJson("/api/v1/pilgrims/{$second['id']}/identity-documents", [
        'document_type' => 'passport',
        'issuing_country' => 'sa',
        'document_number' => 'x-123 4567',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PILGRIM_IDENTITY_DOCUMENT_DUPLICATE');
});

it('allows the same document number for different type or country', function (): void {
    actingAsTenantOwner();
    $pilgrim = spCreatePilgrim();
    spAddDocument($pilgrim['id'], 'X1234567', 'passport', 'SA');
    spAddDocument($pilgrim['id'], 'X1234567', 'national_id', 'SA');
    spAddDocument($pilgrim['id'], 'X1234567', 'passport', 'EG');

    expect(PilgrimIdentityDocument::query()->withoutGlobalScopes()->count())->toBe(3);
});

it('allows the same document in different tenants', function (): void {
    actingAsTenantOwner();
    spAddDocument(spCreatePilgrim()['id'], 'X1234567');

    actingAsTenantOwner();
    spAddDocument(spCreatePilgrim()['id'], 'X1234567');

    expect(PilgrimIdentityDocument::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('enforces identity document uniqueness at the database level', function (): void {
    $owner = actingAsTenantOwner();
    $pilgrim = spCreatePilgrim();
    spAddDocument($pilgrim['id'], 'Z9999999');

    withTenant($owner->tenant, function () use ($pilgrim): void {
        expect(fn () => PilgrimIdentityDocument::query()->create([
            'pilgrim_id' => $pilgrim['id'],
            'document_type' => 'passport',
            'issuing_country' => 'SA',
            'document_number' => 'Z-9999999',
        ]))->toThrow(UniqueConstraintViolationException::class);
    });
});

it('updates and deletes identity documents with audit', function (): void {
    actingAsTenantOwner();
    $pilgrim = spCreatePilgrim();
    $doc = spAddDocument($pilgrim['id'], 'Q1111111');

    spaPatchJson("/api/v1/pilgrim-identity-documents/{$doc['id']}", ['document_number' => 'Q2222222'])
        ->assertOk()
        ->assertJsonPath('data.document_number', 'Q2222222');

    spaDeleteJson("/api/v1/pilgrim-identity-documents/{$doc['id']}")->assertOk();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_UPDATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_DELETED)->exists())->toBeTrue();
});

it('does not mutate the season identity snapshot when the pilgrim identity changes', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim(['full_name' => 'الاسم الأصلي']);
    $doc = spAddDocument($pilgrim['id'], 'P1000001');
    $sp = spRegister($season['id'], $pilgrim['id'], ['pilgrim_identity_document_id' => $doc['id']]);

    spaPatchJson("/api/v1/pilgrims/{$pilgrim['id']}", ['full_name' => 'اسم معدل'])->assertOk();
    spaPatchJson("/api/v1/pilgrim-identity-documents/{$doc['id']}", ['document_number' => 'P2000002'])->assertOk();

    spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertOk()
        ->assertJsonPath('data.identity_snapshot.full_name', 'الاسم الأصلي')
        ->assertJsonPath('data.identity_snapshot.document_number', 'P1000001');
});

it('keeps the snapshot when its source document is deleted', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();
    $doc = spAddDocument($pilgrim['id'], 'P3000003');
    $sp = spRegister($season['id'], $pilgrim['id'], ['pilgrim_identity_document_id' => $doc['id']]);

    spaDeleteJson("/api/v1/pilgrim-identity-documents/{$doc['id']}")->assertOk();

    spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))
        ->assertOk()
        ->assertJsonPath('data.identity_snapshot.document_number', 'P3000003')
        ->assertJsonPath('data.identity_snapshot.source_identity_document_id', null);
});

it('rejects identity document references from another tenant', function (): void {
    $ownerB = actingAsTenantOwner();
    $foreignDoc = spAddDocument(spCreatePilgrim()['id'], 'F0000001');
    $foreignPilgrim = spCreatePilgrim();

    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();

    spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => $pilgrim['id'],
        'pilgrim_identity_document_id' => $foreignDoc['id'],
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonValidationErrors(['pilgrim_identity_document_id']);

    spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => $foreignPilgrim['id'],
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonValidationErrors(['pilgrim_id']);

    spaPatchJson("/api/v1/pilgrim-identity-documents/{$foreignDoc['id']}", ['document_number' => 'HACK'])->assertNotFound();
    spaPostJson("/api/v1/pilgrims/{$foreignPilgrim['id']}/identity-documents", [
        'document_type' => 'passport', 'issuing_country' => 'SA', 'document_number' => 'HACK1',
    ])->assertNotFound();

    expect($ownerB)->not->toBeNull();
});

it('rejects a document that belongs to a different pilgrim of the same tenant', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();
    $otherDoc = spAddDocument(spCreatePilgrim(['full_name' => 'آخر'])['id'], 'D0000001');

    spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => $pilgrim['id'],
        'pilgrim_identity_document_id' => $otherDoc['id'],
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'PILGRIM_IDENTITY_DOCUMENT_INVALID');
});

it('deletes a pilgrim only without participations', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $free = spCreatePilgrim();
    $used = spCreatePilgrim(['full_name' => 'مشارك']);
    spRegister($season['id'], $used['id']);

    spaDeleteJson("/api/v1/pilgrims/{$free['id']}")->assertOk();
    spaDeleteJson("/api/v1/pilgrims/{$used['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_IN_USE');

    expect(Pilgrim::query()->withoutGlobalScopes()->find($free['id']))->toBeNull();
});

it('searches pilgrims by name and normalized document number', function (): void {
    actingAsTenantOwner();
    $a = spCreatePilgrim(['full_name' => 'عبدالله الأول']);
    spCreatePilgrim(['full_name' => 'سالم الثاني']);
    spAddDocument($a['id'], 'K7777777');

    spaGetJson('/api/v1/pilgrims?search=عبدالله')->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/pilgrims?search=k-777 7777')->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/pilgrims?search=k-777')->assertOk()->assertJsonCount(0, 'data');
});

it('isolates pilgrims across tenants', function (): void {
    actingAsTenantOwner();
    $foreign = spCreatePilgrim();

    actingAsTenantOwner();
    spCreatePilgrim();

    spaGetJson('/api/v1/pilgrims')->assertOk()->assertJsonCount(1, 'data');
    spaGetJson("/api/v1/pilgrims/{$foreign['id']}")->assertNotFound();
    spaPatchJson("/api/v1/pilgrims/{$foreign['id']}", ['full_name' => 'x'])->assertNotFound();
    spaDeleteJson("/api/v1/pilgrims/{$foreign['id']}")->assertNotFound();

    expect(Tenant::query()->count())->toBe(2);
});
