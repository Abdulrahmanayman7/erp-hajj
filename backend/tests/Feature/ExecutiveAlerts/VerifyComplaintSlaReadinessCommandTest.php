<?php

use Illuminate\Support\Facades\Config;

it('verifies U03 readiness without activating when unset', function (): void {
    Config::set('app.env', 'local');
    Config::set('executive_alerts.complaint_sla_activated_at', null);

    $this->artisan('executive-alerts:verify-complaint-sla-readiness')
        ->expectsOutputToContain('policy_active_default=false')
        ->expectsOutputToContain('Production activation NOT authorized')
        ->assertSuccessful();
});

it('supports disposable in-process smoke activation outside production', function (): void {
    Config::set('app.env', 'testing');
    Config::set('executive_alerts.complaint_sla_activated_at', null);

    $this->artisan('executive-alerts:verify-complaint-sla-readiness', ['--smoke-activate' => true])
        ->expectsOutputToContain('In-process smoke activation only')
        ->expectsOutputToContain('policy_active_after_smoke=true')
        ->assertSuccessful();
});

it('refuses readiness verification in production', function (): void {
    Config::set('app.env', 'production');

    $this->artisan('executive-alerts:verify-complaint-sla-readiness')
        ->expectsOutputToContain('REFUSED')
        ->assertFailed();
});
