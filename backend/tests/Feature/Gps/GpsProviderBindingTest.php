<?php

use App\Modules\Gps\Contracts\GpsProviderInterface;
use App\Modules\Gps\Providers\NullGpsProvider;

it('binds GpsProviderInterface to NullGpsProvider by default', function (): void {
    expect(config('gps.provider'))->toBe('null');
    expect(app(GpsProviderInterface::class))->toBeInstanceOf(NullGpsProvider::class);
});
