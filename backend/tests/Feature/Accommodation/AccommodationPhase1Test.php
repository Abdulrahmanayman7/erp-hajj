<?php

use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\SeasonPilgrims\Models\Season;
use Laravel\Sanctum\Sanctum;

it('searches Arabic pilgrim names before pagination without exposing unrelated records', function (): void {
    [$actor, $season] = accGateCActor(['accommodation.view', 'accommodation.draft', 'accommodation.manage_facilities', 'accommodation.manage_season_allocation', 'accommodation.manage_scopes', 'season_pilgrims.view']);
    $world = accGateCWorld($actor, $season);
    $last = null;
    for ($i = 0; $i < 16; $i++) {
        $pilgrim = accGateCPilgrim($actor, $season);
        withTenant($actor->tenant, fn () => $pilgrim->pilgrim->update(['full_name' => $i === 15 ? 'عبدالله اختبار البحث العربي' : 'حاج آخر '.$i]));
        $last = accGateCDraft($world, $pilgrim, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    }
    spaGetJson('/api/v1/accommodation/assignments?per_page=15', $world['header'])->assertOk()->assertJsonPath('meta.total', 16);
    spaGetJson('/api/v1/accommodation/assignments?search='.urlencode('عبدالله اختبار').'&booking_status=draft', $world['header'])
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $last['id'])
        ->assertJsonPath('data.0.pilgrim_label', 'عبدالله اختبار البحث العربي');
    spaGetJson('/api/v1/accommodation/assignments?search='.urlencode('عبدالله اختبار').'&booking_status=confirmed', $world['header'])
        ->assertOk()->assertJsonPath('meta.total', 0);
    spaGetJson('/api/v1/accommodation/assignments?booking_status=invalid', $world['header'])->assertUnprocessable()
        ->assertJsonPath('message', 'يرجى مراجعة الحقول المدخلة.');
});

it('does not disclose pilgrim labels or name search without the existing pilgrim permission', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season);
    $pilgrim = accGateCPilgrim($actor, $season);
    withTenant($actor->tenant, fn () => $pilgrim->pilgrim->update(['full_name' => 'اسم لا يسمح بكشفه']));
    accGateCDraft($world, $pilgrim, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    spaGetJson('/api/v1/accommodation/assignments', $world['header'])->assertOk()->assertJsonMissingPath('data.0.pilgrim_label');
    spaGetJson('/api/v1/accommodation/assignments?search='.urlencode('اسم لا يسمح'), $world['header'])->assertOk()->assertJsonPath('meta.total', 0);
    withTenant($actor->tenant, fn () => AccommodationFacilityScope::query()->where('user_id', $actor->id)->update(['revoked_at' => now()]));
    spaGetJson('/api/v1/accommodation/assignments?search='.$pilgrim->id, $world['header'])->assertOk()->assertJsonPath('meta.total', 0);
});

it('keeps the seasonal and tenant boundaries when searching assignments', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season);
    $first = accGateCDraft($world, accGateCPilgrim($actor, $season), '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    $otherSeason = withTenant($actor->tenant, fn () => Season::factory()->create());
    $otherWorld = accGateCWorld($actor, $otherSeason);
    $other = accGateCDraft($otherWorld, accGateCPilgrim($actor, $otherSeason), '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    spaGetJson('/api/v1/accommodation/assignments?search='.$other['id'], $world['header'])->assertOk()->assertJsonPath('meta.total', 0);
    [$outsider, $outsiderSeason] = accGateCActor();
    spaGetJson('/api/v1/accommodation/assignments?search='.$first['id'], seasonHeader($outsiderSeason->id))->assertOk()->assertJsonPath('meta.total', 0);
    Sanctum::actingAs($actor);
    spaGetJson('/api/v1/accommodation/assignments?search='.$first['id'], $world['header'])->assertOk()->assertJsonPath('meta.total', 1);
});

it('keeps manage allocation permission separate from read access', function (): void {
    [$actor, $season] = accGateCActor(['accommodation.manage_season_allocation']);
    spaGetJson('/api/v1/accommodation/season-units', seasonHeader($season->id))->assertForbidden();
    spaGetJson('/api/v1/accommodation/assignments', seasonHeader($season->id))->assertForbidden();
});

it('returns Arabic validation and missing-resource messages', function (): void {
    [$actor, $season] = accGateCActor();
    $header = seasonHeader($season->id);
    $response = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel'], $header)->assertUnprocessable();
    expect($response->json('errors.code'))->toBeNull(); // Generated on create; only the name is required.
    expect($response->json('errors.name.0'))->toContain('الاسم');
    spaGetJson('/api/v1/accommodation/facilities/999999999', $header)->assertNotFound()
        ->assertJsonPath('message', 'السجل المطلوب غير موجود أو غير متاح.');
});
