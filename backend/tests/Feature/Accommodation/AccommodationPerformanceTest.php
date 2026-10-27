<?php

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Actions\AccommodationConfirmationActions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('loads shared confirmation references once per batch instead of once per pilgrim', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 10);
    $family = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id, 'name' => 'Performance regression family',
    ]));
    $ids = [];
    for ($i = 0; $i < 6; $i++) {
        $ids[] = accGateCDraft($world, accGateCPilgrim($actor, $season, familyGroupId: $family->id),
            '2027-06-01T00:00:00Z', '2027-06-02T00:00:00Z')['id'];
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
            'season_family_group_id' => $family->id, 'assignment_ids' => $ids,
        ], $world['header'])->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
    } finally {
        DB::disableQueryLog();
    }
    foreach (['season_pilgrims', 'pilgrims', 'accommodation_season_facilities'] as $table) {
        $reads = $queries->filter(fn ($sql) => preg_match('/^select .* from ["`]'.$table.'["`]/i', $sql));
        expect($reads->count())->toBeLessThanOrEqual(1, $table.' should be batch-loaded');
    }
    expect($queries->filter(fn ($sql) => str_contains($sql, 'accommodation_pilgrim_locks'))->count())
        ->toBeLessThanOrEqual(2, 'Anchor creation and locking should be batched');
    withTenant($actor->tenant, fn () => expect(AccommodationAssignment::query()->whereIn('id', $ids)
        ->where('booking_status', 'confirmed')->count())->toBe(6));
});

it('does not reject a batch because of a historical peak outside its disjoint windows', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 2);
    $family = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id, 'name' => 'Disjoint windows family',
    ]));
    $ids = [];
    foreach ([['01:00', '02:00'], ['08:00', '09:00']] as [$start, $end]) {
        $ids[] = accGateCDraft($world, accGateCPilgrim($actor, $season, familyGroupId: $family->id),
            '2027-06-01T'.$start.':00Z', '2027-06-01T'.$end.':00Z')['id'];
    }
    for ($i = 0; $i < 3; $i++) {
        $row = accGateCDraft($world, accGateCPilgrim($actor, $season, familyGroupId: $family->id),
            '2027-06-01T04:00:00Z', '2027-06-01T06:00:00Z');
        withTenant($actor->tenant, fn () => AccommodationAssignment::query()->findOrFail($row['id'])->update([
            'booking_status' => 'completed', 'operational_status' => 'checked_out',
            'checkout_source' => 'staff', 'family_group_id' => $family->id,
        ]));
    }
    spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
        'season_family_group_id' => $family->id, 'assignment_ids' => $ids,
    ], $world['header'])->assertOk()->assertJsonCount(2, 'data');
});

it('rolls back all assignments and sensitive audit rows when a batch audit fails', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $ids = [];
    for ($i = 0; $i < 2; $i++) {
        $ids[] = accGateCDraft($world, accGateCPilgrim($actor, $season),
            '2027-06-01T00:00:00Z', '2027-06-02T00:00:00Z')['id'];
    }
    $seen = 0;
    Event::listen(AuthorizationSecurityEvent::class, function ($event) use (&$seen): void {
        if ($event->name === AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED && ++$seen === 2) {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    expect(fn () => withTenant($actor->tenant, fn () => app(SeasonContext::class)->runInSeason($season,
        fn () => app(AccommodationConfirmationActions::class)->confirmMany($actor, $ids, Request::create('/test', 'POST')),
    )))->toThrow(RuntimeException::class, 'Synthetic audit failure');
    withTenant($actor->tenant, function () use ($ids, $actor): void {
        expect(AccommodationAssignment::query()->whereIn('id', $ids)->where('booking_status', 'draft')->count())->toBe(2);
        expect(AuditLog::query()->where('tenant_id', $actor->tenant_id)
            ->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED)->count())->toBe(0);
    });
});
