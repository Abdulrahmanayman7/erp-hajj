<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Authorization\Models\Role;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissions
 * @return array{0: User, 1: Season}
 */
function accActor(array $permissions, string $status = 'draft'): array
{
    actingAsTenantOwner();
    $actor = actingAsSameTenantUserWith($permissions);
    $season = withTenant($actor->tenant, function () use ($status): Season {
        $factory = Season::factory();

        return match ($status) {
            'active' => $factory->active()->create(),
            'closed' => $factory->closed()->create(),
            default => $factory->create(),
        };
    });

    return [$actor, $season];
}

/**
 * @return array<string, string>
 */
function accHeader(Season $season): array
{
    return seasonHeader($season->id);
}

it('registers accommodation permissions without role-template grants', function (): void {
    $names = collect(PermissionCatalog::definitions())
        ->pluck('name')
        ->filter(fn (string $name): bool => str_starts_with($name, 'accommodation.'))
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'accommodation.arrive',
        'accommodation.cancel_assignment',
        'accommodation.check_in',
        'accommodation.check_out',
        'accommodation.confirm',
        'accommodation.draft',
        'accommodation.manage_facilities',
        'accommodation.manage_scopes',
        'accommodation.manage_season_allocation',
        'accommodation.relocate',
        'accommodation.view',
    ]);

    foreach (PermissionCatalog::roleTemplates() as $code => $template) {
        $granted = array_values(array_filter(
            (array) $template['permissions'],
            fn (string $name): bool => str_starts_with($name, 'accommodation.'),
        ));
        expect($granted)->toBe([], $code);
    }

    $owner = actingAsTenantOwner();
    withTenant(Tenant::query()->findOrFail($owner->tenant_id), function (): void {
        $role = Role::query()->where('code', 'tenant_owner')->firstOrFail();
        expect($role->permissions()->where('name', 'like', 'accommodation.%')->count())->toBe(0);
    });

    $season = withTenant($owner->tenant, fn () => Season::factory()->create());
    spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel',
        'code' => 'H-OWNER',
        'name' => 'فندق المالك',
    ], accHeader($season))->assertForbidden()->assertJsonPath('code', 'AUTHORIZATION_DENIED');

    $uris = collect(Route::getRoutes())->map(fn ($route): string => $route->uri());
    expect($uris->contains(fn (string $uri): bool => str_contains($uri, 'accommodation') && str_contains($uri, 'extend')))->toBeFalse();
    expect($uris->contains('api/v1/accommodation/assignments/{assignment}/confirm'))->toBeTrue();
    expect($uris->contains('api/v1/accommodation/assignments/confirm-family'))->toBeTrue();
    expect($uris->contains('api/v1/accommodation/assignments/{assignment}/check-in'))->toBeTrue();
    expect($uris->contains('api/v1/accommodation/assignments/{assignment}/relocate'))->toBeTrue();
});

it('rejects unauthenticated accommodation reads', function (): void {
    spaGetJson('/api/v1/accommodation/facilities')->assertUnauthorized();
});

it('bootstraps a facility and scope before any grant exists', function (): void {
    [$manager, $season] = accActor([
        'accommodation.manage_facilities',
        'accommodation.manage_scopes',
    ]);
    $header = accHeader($season);

    spaGetJson('/api/v1/accommodation/facilities', $header)->assertForbidden();

    $facility = spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel',
        'code' => 'H1',
        'name' => 'فندق النور',
        'tenant_id' => 999,
    ], $header)->assertUnprocessable();

    $facility = spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel',
        'code' => 'H1',
        'name' => 'فندق النور',
    ], $header)->assertCreated()->assertJsonPath('data.code', 'H1')->json('data');

    $viewer = actingAsSameTenantUserWith(['accommodation.view']);
    spaGetJson('/api/v1/accommodation/facilities/'.$facility['id'], $header)
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
    spaGetJson('/api/v1/accommodation/facilities', $header)->assertOk()->assertJsonPath('meta.total', 0);

    Sanctum::actingAs($manager);
    $grant = spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $viewer->id,
        'facility_id' => $facility['id'],
    ], $header)->assertCreated()->json('data');

    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $viewer->id,
        'facility_id' => $facility['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_SCOPE_EXISTS');

    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => platformUser()->id,
        'facility_id' => $facility['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_GRANTEE_TENANT');

    $foreign = tenantUser(Tenant::factory()->create());
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $foreign->id,
        'facility_id' => $facility['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_GRANTEE_TENANT');

    Sanctum::actingAs($viewer);
    spaGetJson('/api/v1/accommodation/facilities/'.$facility['id'], $header)->assertOk();

    Sanctum::actingAs($manager);
    spaPostJson('/api/v1/accommodation/facility-scopes/'.$grant['id'].'/revoke', [], $header)->assertOk();
    spaPostJson('/api/v1/accommodation/facility-scopes/'.$grant['id'].'/revoke', [], $header)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_SCOPE_REVOKED');

    Sanctum::actingAs($viewer);
    spaGetJson('/api/v1/accommodation/facilities/'.$facility['id'], $header)->assertForbidden();

    Sanctum::actingAs($manager);
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $viewer->id,
        'facility_id' => $facility['id'],
    ], $header)->assertCreated();

    expect(DB::table('accommodation_facility_scopes')->where('user_id', $viewer->id)->count())->toBe(2)
        ->and(DB::table('accommodation_facility_scopes')->where('user_id', $viewer->id)->whereNull('revoked_at')->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_CREATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_SCOPE_GRANTED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_SCOPE_REVOKED)->exists())->toBeTrue();
});

it('lets a season supervisor see every facility without granting manage permissions', function (): void {
    [$manager, $season] = accActor([
        'accommodation.manage_facilities',
        'accommodation.manage_scopes',
    ]);
    $header = accHeader($season);
    $first = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'code' => 'A', 'name' => 'أ'], $header)->assertCreated()->json('data');
    $second = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'camp', 'code' => 'B', 'name' => 'ب'], $header)->assertCreated()->json('data');

    $viewer = actingAsSameTenantUserWith(['accommodation.view', 'accommodation.draft']);
    Sanctum::actingAs($manager);
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $viewer->id,
        'facility_id' => $first['id'],
    ], $header)->assertCreated();

    Sanctum::actingAs($viewer);
    spaGetJson('/api/v1/accommodation/facilities', $header)->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/accommodation/facilities/'.$second['id'], $header)->assertForbidden();
    spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'code' => 'C', 'name' => 'ج'], $header)->assertForbidden();

    Sanctum::actingAs($manager);
    $supervisor = spaPostJson('/api/v1/accommodation/supervisor-scopes', [
        'user_id' => $viewer->id,
    ], $header)->assertCreated()->json('data');

    Sanctum::actingAs($viewer);
    spaGetJson('/api/v1/accommodation/facilities', $header)->assertOk()->assertJsonPath('meta.total', 2);

    Sanctum::actingAs($manager);
    spaPostJson('/api/v1/accommodation/supervisor-scopes/'.$supervisor['id'].'/revoke', [], $header)->assertOk();
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_SUPERVISOR_SCOPE_GRANTED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_SUPERVISOR_SCOPE_REVOKED)->exists())->toBeTrue();
});

it('keeps master data stable and rejects cycles, cross-facility parents, and referenced deletes', function (): void {
    [$actor, $season] = accActor(['accommodation.manage_facilities', 'accommodation.view', 'accommodation.manage_scopes']);
    $header = accHeader($season);
    $hotel = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'code' => 'HTL', 'name' => 'الفندق'], $header)->assertCreated()->json('data');
    $camp = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'camp', 'code' => 'CMP', 'name' => 'المخيم'], $header)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/facility-scopes', ['user_id' => $actor->id, 'facility_id' => $hotel['id']], $header)->assertCreated();
    spaPostJson('/api/v1/accommodation/facility-scopes', ['user_id' => $actor->id, 'facility_id' => $camp['id']], $header)->assertCreated();

    $building = spaPostJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/structures', [
        'kind' => 'building',
        'name' => 'المبنى',
    ], $header)->assertCreated()->json('data');
    $floor = spaPostJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/structures', [
        'kind' => 'floor',
        'name' => 'الدور',
        'parent_id' => $building['id'],
    ], $header)->assertCreated()->json('data');
    $foreign = spaPostJson('/api/v1/accommodation/facilities/'.$camp['id'].'/structures', [
        'kind' => 'building',
        'name' => 'مبنى المخيم',
    ], $header)->assertCreated()->json('data');

    spaGetJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/structures?per_page=50', $header)
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 2);

    spaPatchJson('/api/v1/accommodation/structures/'.$building['id'], [
        'parent_id' => $floor['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_STRUCTURE_CYCLE');

    spaPostJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/structures', [
        'kind' => 'floor',
        'name' => 'دور غريب',
        'parent_id' => $foreign['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');

    $unit = spaPostJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '405',
        'structure_id' => $floor['id'],
    ], $header)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/facilities/'.$hotel['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '406',
        'structure_id' => $foreign['id'],
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');

    $position = spaPostJson('/api/v1/accommodation/units/'.$unit['id'].'/positions', [
        'code' => 'B1',
    ], $header)->assertCreated()->json('data');

    spaPatchJson('/api/v1/accommodation/facilities/'.$hotel['id'], [
        'name' => 'الفندق المحدّث',
        'is_active' => false,
    ], $header)->assertOk()->assertJsonPath('data.name', 'الفندق المحدّث');

    spaDeleteJson('/api/v1/accommodation/facilities/'.$hotel['id'], $header)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_REFERENCED');
    spaDeleteJson('/api/v1/accommodation/positions/'.$position['id'], $header)->assertOk();

    $otherTenant = Tenant::factory()->create();
    $foreignFacility = withTenant($otherTenant, fn () => AccommodationFacility::query()->create([
        'kind' => 'hotel',
        'code' => 'X',
        'name' => 'أجنبي',
    ]));
    spaGetJson('/api/v1/accommodation/facilities/'.$foreignFacility->id, $header)->assertNotFound();

    $otherSeason = withTenant($actor->tenant, fn () => Season::factory()->create([
        'code' => 'H1601',
        'name' => 'موسم 1601',
        'hajj_year' => 1601,
    ]));
    spaGetJson('/api/v1/accommodation/facilities/'.$hotel['id'], accHeader($otherSeason))->assertForbidden();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_DEACTIVATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_POSITION_DELETED)->exists())->toBeTrue()
        ->and(DB::table('accommodation_facilities')->where('id', $hotel['id'])->exists())->toBeTrue();
});

it('allocates season inventory in draft and active seasons and rejects closed writes', function (): void {
    [$actor, $draft] = accActor(['accommodation.manage_facilities', 'accommodation.manage_season_allocation']);
    $header = accHeader($draft);
    $facility = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'code' => 'S1', 'name' => 'موسمي'], $header)->assertCreated()->json('data');
    $other = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'camp', 'code' => 'S2', 'name' => 'آخر'], $header)->assertCreated()->json('data');
    $unit = spaPostJson('/api/v1/accommodation/facilities/'.$facility['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '12',
    ], $header)->assertCreated()->json('data');
    $otherUnit = spaPostJson('/api/v1/accommodation/facilities/'.$other['id'].'/units', [
        'unit_kind' => 'tent',
        'code' => '9',
    ], $header)->assertCreated()->json('data');
    $bed = spaPostJson('/api/v1/accommodation/units/'.$unit['id'].'/positions', ['code' => '1'], $header)->assertCreated()->json('data');

    $seasonFacility = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
        'audience' => 'family',
    ], $header)->assertCreated()->assertJsonPath('data.capacity_reserved', false)->json('data');

    spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $seasonFacility['id'],
        'unit_id' => $otherUnit['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 4,
        'audience' => 'family',
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');

    $seasonUnit = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $seasonFacility['id'],
        'unit_id' => $unit['id'],
        'allocation_mode' => 'position',
        'capacity' => 4,
        'audience' => 'male',
    ], $header)->assertCreated()->assertJsonPath('data.capacity', 4)->json('data');

    spaPostJson('/api/v1/accommodation/season-positions', [
        'season_unit_id' => $seasonUnit['id'],
        'position_id' => $bed['id'],
    ], $header)->assertCreated();

    $active = withTenant($actor->tenant, fn () => Season::factory()->active()->create([
        'code' => 'H1602',
        'name' => 'موسم 1602',
        'hajj_year' => 1602,
    ]));
    spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
    ], accHeader($active))->assertCreated();

    $closed = withTenant($actor->tenant, fn () => Season::factory()->closed()->create([
        'code' => 'H1603',
        'name' => 'موسم 1603',
        'hajj_year' => 1603,
    ]));
    spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
    ], accHeader($closed))->assertUnprocessable()->assertJsonPath('code', 'SEASON_CLOSED');
    spaGetJson('/api/v1/accommodation/season-facilities/'.$seasonFacility['id'], accHeader($closed))->assertNotFound();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_SEASON_UNIT_CREATED)->exists())->toBeTrue();
});

it('stores overlapping drafts in UTC without consuming capacity', function (): void {
    [$actor, $season] = accActor([
        'accommodation.manage_facilities',
        'accommodation.manage_season_allocation',
        'accommodation.manage_scopes',
        'accommodation.draft',
    ]);
    $header = accHeader($season);
    $facility = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'code' => 'DR', 'name' => 'مسودات'], $header)->assertCreated()->json('data');
    $unit = spaPostJson('/api/v1/accommodation/facilities/'.$facility['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '1',
    ], $header)->assertCreated()->json('data');
    $bed = spaPostJson('/api/v1/accommodation/units/'.$unit['id'].'/positions', ['code' => 'A'], $header)->assertCreated()->json('data');
    $seasonFacility = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
    ], $header)->assertCreated()->json('data');
    $seasonUnit = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $seasonFacility['id'],
        'unit_id' => $unit['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 4,
        'audience' => 'family',
    ], $header)->assertCreated()->json('data');
    $seasonPosition = spaPostJson('/api/v1/accommodation/season-positions', [
        'season_unit_id' => $seasonUnit['id'],
        'position_id' => $bed['id'],
    ], $header)->assertCreated()->json('data');

    $participation = withTenant($actor->tenant, function () use ($season) {
        $pilgrim = Pilgrim::factory()->create();

        return SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-ACC-1',
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    });

    spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit['id'],
        'season_position_id' => $seasonPosition['id'],
        'starts_at' => '2027-06-01T22:00:00+03:00',
        'original_ends_at' => '2027-06-02T01:00:00+03:00',
    ], $header)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $actor->id,
        'facility_id' => $facility['id'],
    ], $header)->assertCreated();

    $first = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit['id'],
        'season_position_id' => $seasonPosition['id'],
        'starts_at' => '2027-06-01T22:00:00+03:00',
        'original_ends_at' => '2027-06-02T01:00:00+03:00',
    ], $header)->assertCreated()
        ->assertJsonPath('data.booking_status', 'draft')
        ->assertJsonPath('data.capacity_reserved', false)
        ->assertJsonPath('data.starts_at', '2027-06-01T19:00:00Z')
        ->assertJsonPath('data.original_ends_at', '2027-06-01T22:00:00Z')
        ->assertJsonPath('data.effective_ends_at', '2027-06-01T22:00:00Z')
        ->json('data');

    expect($first['operational_status'] ?? null)->toBeNull()
        ->and($first['checkout_source'] ?? null)->toBeNull()
        ->and($first)->not->toHaveKey('family_group_id');

    spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit['id'],
        'starts_at' => '2027-06-01T20:00:00Z',
        'original_ends_at' => '2027-06-01T23:00:00Z',
        'booking_status' => 'confirmed',
    ], $header)->assertUnprocessable();

    spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit['id'],
        'starts_at' => '2027-06-01T20:00:00Z',
        'original_ends_at' => '2027-06-01T20:00:00Z',
    ], $header)->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_INTERVAL_INVALID');

    $second = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit['id'],
        'starts_at' => '2027-06-01T20:00:00Z',
        'original_ends_at' => '2027-06-01T23:00:00Z',
    ], $header)->assertCreated()->json('data');

    $otherSeason = withTenant($actor->tenant, function () {
        $other = Season::factory()->create([
            'code' => 'H1604',
            'name' => 'موسم 1604',
            'hajj_year' => 1604,
        ]);
        $pilgrim = Pilgrim::factory()->create();
        SeasonPilgrim::query()->create([
            'season_id' => $other->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-ACC-2',
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        return $other;
    });

    spaGetJson('/api/v1/accommodation/assignments/'.$first['id'], accHeader($otherSeason))->assertNotFound();

    spaDeleteJson('/api/v1/accommodation/assignments/'.$second['id'], $header)->assertOk();

    withTenant($actor->tenant, function () use ($seasonUnit, $participation): void {
        expect(AccommodationSeasonUnit::query()->find($seasonUnit['id'])?->capacity)->toBe(4)
            ->and(SeasonPilgrim::query()->find($participation->id)?->status->value)->toBe('registered')
            ->and(DB::table('accommodation_assignments')->where('season_unit_id', $seasonUnit['id'])->count())->toBe(1)
            ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CREATED)->count())->toBe(2)
            ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_DELETED)->exists())->toBeTrue();
    });
});
