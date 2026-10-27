<?php

use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

function capacityIntervals(array $pairs): Collection
{
    return collect($pairs)->map(fn ($pair) => (object) [
        'starts_at' => CarbonImmutable::parse('2027-06-01 '.$pair[0], 'UTC'),
        'effective_ends_at' => CarbonImmutable::parse('2027-06-01 '.$pair[1], 'UTC'),
    ]);
}

it('uses true simultaneous occupancy rather than pairwise overlap counts', function (array $pairs, int $expected): void {
    $guard = new AccommodationCapacityGuard;
    $rows = capacityIntervals($pairs);
    foreach ($rows as $candidate) {
        expect($guard->peakWithinWindow($rows, $candidate->starts_at, $candidate->effective_ends_at))->toBeLessThanOrEqual($expected);
    }
    expect($guard->peakWithinWindow($rows, CarbonImmutable::parse('2027-06-01 00:00 UTC'), CarbonImmutable::parse('2027-06-02 00:00 UTC')))->toBe($expected);
})->with([
    'long stay plus disjoint short stays regression' => [[['01:00', '10:00'], ['01:00', '03:00'], ['07:00', '09:00']], 2],
    'overlapping' => [[['01:00', '05:00'], ['02:00', '06:00'], ['03:00', '07:00']], 3],
    'disjoint' => [[['01:00', '02:00'], ['03:00', '04:00']], 1],
    'touching half-open endpoints' => [[['01:00', '03:00'], ['03:00', '05:00']], 1],
    'identical' => [[['01:00', '03:00'], ['01:00', '03:00']], 2],
]);

it('clips existing reservations to the candidate window', function (): void {
    $guard = new AccommodationCapacityGuard;
    $rows = capacityIntervals([['01:00', '10:00'], ['01:00', '03:00'], ['07:00', '09:00']]);
    expect($guard->peakWithinWindow($rows, CarbonImmutable::parse('2027-06-01 04:00 UTC'), CarbonImmutable::parse('2027-06-01 06:00 UTC'), 1))->toBe(2);
});
