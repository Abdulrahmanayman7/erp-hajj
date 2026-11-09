<?php

use App\Modules\SeasonPilgrims\Models\Season;

it('returns 422 SEASON_REQUIRED when season_id is missing — no silent Active-season fallback', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    kpiSeason($tenant, 'active');

    kpiGet()
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED')
        ->assertJsonValidationErrors('season_id');
});

it('returns 422 SEASON_REQUIRED for a blank season_id', function (): void {
    actingAsTenantOwner();

    spaGetJson('/api/v1/operational-kpis?season_id=')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED');
});

it('returns a plain validation error for a malformed season_id', function (): void {
    actingAsTenantOwner();

    spaGetJson('/api/v1/operational-kpis?season_id=abc')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    spaGetJson('/api/v1/operational-kpis?season_id=0')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('returns 404 for an unknown season', function (): void {
    actingAsTenantOwner();

    kpiGet(999999)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
});

it('serves any season state explicitly selected, with Arabic status labels', function (): void {
    $tenant = actingAsTenantOwner()->tenant;

    $labels = ['draft' => 'مسودة', 'active' => 'نشط', 'closed' => 'مغلق'];

    foreach ($labels as $status => $label) {
        $season = kpiSeason($tenant, $status);

        kpiGet($season->id)
            ->assertOk()
            ->assertJsonPath('data.season.id', $season->id)
            ->assertJsonPath('data.season.name', $season->name)
            ->assertJsonPath('data.season.status', $status)
            ->assertJsonPath('data.season.status_label', $label)
            ->assertJsonPath('meta.season_id', $season->id);
    }
});

it('reads KPI cards for a closed season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'closed');

    $response = kpiGet($season->id)->assertOk()->assertJsonPath('data.response_state', 'ok');

    expect(kpiMap($response))->toHaveKey('T01');
});

it('works for a tenant with no active season when an explicit season is given', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    expect(withTenant($tenant, fn (): int => Season::query()->where('status', 'active')->count()))->toBe(0);

    kpiGet($season->id)->assertOk();
});

it('emits the response envelope with a single timezone-aware generated_at', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    $response = kpiGet($season->id)->assertOk();

    $response->assertJsonStructure([
        'success',
        'data' => ['kpis', 'season' => ['id', 'name', 'status', 'status_label'], 'response_state'],
        'meta' => ['generated_at', 'timezone', 'season_id'],
    ]);

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('meta.timezone'))->toBe($tenant->timezone ?: config('app.timezone', 'Asia/Riyadh'))
        ->and(Carbon\Carbon::parse($response->json('meta.generated_at'))->getTimezone()->getName())
        ->not->toBe('');

    foreach (kpiMap($response) as $kpi) {
        expect(array_keys($kpi))->toBe(['id', 'domain', 'label', 'value', 'display_value', 'state', 'href']);
    }
});

it('does not cache KPI results between requests', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    expect(kpiMap(kpiGet($season->id))['J01']['value'])->toBe(0);

    kpiProject($tenant, $season, 'active');

    expect(kpiMap(kpiGet($season->id))['J01']['value'])->toBe(1);
});
