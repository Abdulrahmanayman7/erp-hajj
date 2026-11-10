<?php

use App\Modules\ExecutiveAlerts\Support\ComplaintSlaEvaluator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit S11 markers for integrations that must not be claimed green.
 * These are documentation-as-tests: they assert the BLOCKED precondition, not product readiness.
 */
it('S11 BLOCKED: U03 complaint SLA remains inactive without Owner activation', function (): void {
    Config::set('executive_alerts.complaint_sla_activated_at', null);

    expect(app(ComplaintSlaEvaluator::class)->isPolicyActive())->toBeFalse();
});

it('S11 BLOCKED: GPS history retention table is absent (G5)', function (): void {
    expect(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();
});

it('S11 BLOCKED: live GPS provider is not configured as a real vendor binding (G4)', function (): void {
    $provider = (string) config('gps.provider', 'null');

    expect($provider)->toBe('null');
});
