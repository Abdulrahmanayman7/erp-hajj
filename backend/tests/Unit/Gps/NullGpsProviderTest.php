<?php

use App\Modules\Gps\Providers\NullGpsProvider;

it('NullGpsProvider never invents coordinates', function (): void {
    $provider = new NullGpsProvider;

    expect($provider->fetchLatest(1))->toBeNull()
        ->and($provider->fetchLatest(999))->toBeNull();
});
