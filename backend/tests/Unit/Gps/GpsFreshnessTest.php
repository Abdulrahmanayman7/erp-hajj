<?php

use App\Modules\Gps\Enums\GpsFreshnessClass;
use App\Modules\Gps\Support\GpsFreshness;
use Illuminate\Support\Carbon;

it('classifies missing timestamps as unavailable', function (): void {
    expect(GpsFreshness::classify(null)->value)->toBe(GpsFreshnessClass::Unavailable->value);
});

it('classifies age within 5 minutes as real', function (): void {
    $now = Carbon::parse('2026-11-10T12:00:00+03:00');
    $recorded = $now->copy()->subMinutes(4);

    expect(GpsFreshness::classify($recorded, $now, 300)->value)->toBe(GpsFreshnessClass::Real->value);
});

it('classifies age over 5 minutes as stale', function (): void {
    $now = Carbon::parse('2026-11-10T12:00:00+03:00');
    $recorded = $now->copy()->subMinutes(6);

    expect(GpsFreshness::classify($recorded, $now, 300)->value)->toBe(GpsFreshnessClass::Stale->value);
});
