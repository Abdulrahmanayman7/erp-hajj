<?php

use App\Modules\Gps\Contracts\GpsLocationHistoryWriterInterface;
use App\Modules\Gps\Contracts\GpsProviderInterface;
use App\Modules\Gps\Exceptions\GpsProviderNotApprovedException;
use App\Modules\Gps\Providers\NullGpsProvider;
use Illuminate\Support\Facades\Schema;

it('binds GpsProviderInterface to NullGpsProvider by default', function (): void {
    expect(config('gps.provider'))->toBe('null');
    expect(app(GpsProviderInterface::class))->toBeInstanceOf(NullGpsProvider::class);
});

it('fails closed when GPS_PROVIDER names an unapproved live binding (G4 prep)', function (): void {
    config(['gps.provider' => 'invented-vendor']);
    app()->forgetInstance(GpsProviderInterface::class);

    expect(fn () => app(GpsProviderInterface::class))
        ->toThrow(GpsProviderNotApprovedException::class);
});

it('does not bind a G5 history writer and keeps history table absent', function (): void {
    expect(app()->bound(GpsLocationHistoryWriterInterface::class))->toBeFalse()
        ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();
});
