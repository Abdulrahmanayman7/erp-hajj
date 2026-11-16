<?php

use App\Modules\Gps\Contracts\GpsLocationHistoryWriterInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('keeps G5 history contracts disabled with TBD retention', function (): void {
    expect(config('gps.history.enabled'))->toBeFalse()
        ->and(config('gps.history.retention_days'))->toBeNull()
        ->and(config('gps.history.deletion_policy'))->toBeNull()
        ->and(config('gps.history.consent_required'))->toBeTrue()
        ->and(config('gps.history.planned_permissions'))->toBe([
            'gps.history.view',
            'gps.history.export',
            'gps.history.delete',
        ]);
});

it('does not bind a history writer or expose history API routes', function (): void {
    expect(app()->bound(GpsLocationHistoryWriterInterface::class))->toBeFalse()
        ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();

    $historyUris = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri): bool => str_contains($uri, 'gps') && str_contains($uri, 'history'))
        ->values()
        ->all();

    expect($historyUris)->toBe([]);
});
