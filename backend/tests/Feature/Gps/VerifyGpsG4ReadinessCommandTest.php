<?php

use Illuminate\Support\Facades\Config;

it('verifies G4 readiness posture outside production', function (): void {
    Config::set('app.env', 'local');
    Config::set('gps.provider', 'null');
    Config::set('gps.live_outbound_authorized', false);
    Config::set('gps.live_outbound_authorized_in_production', false);
    Config::set('gps.history.enabled', false);
    Config::set('gps.history.retention_days', null);

    $this->artisan('gps:verify-g4-readiness')
        ->expectsOutputToContain('default_provider_is_null=PASS')
        ->expectsOutputToContain('live_outbound_default_closed=PASS')
        ->expectsOutputToContain('history_writer_unbound=PASS')
        ->expectsOutputToContain('Live vendor binding NOT authorized')
        ->assertSuccessful();
});

it('refuses G4 readiness verification in production', function (): void {
    Config::set('app.env', 'production');

    $this->artisan('gps:verify-g4-readiness')
        ->expectsOutputToContain('REFUSED')
        ->assertFailed();
});

it('fails when history retention contract is inventively enabled', function (): void {
    Config::set('app.env', 'testing');
    Config::set('gps.provider', 'null');
    Config::set('gps.live_outbound_authorized', false);
    Config::set('gps.live_outbound_authorized_in_production', false);
    Config::set('gps.history.enabled', true);
    Config::set('gps.history.retention_days', 30);

    $this->artisan('gps:verify-g4-readiness')
        ->expectsOutputToContain('history_disabled=FAIL')
        ->assertFailed();
});
