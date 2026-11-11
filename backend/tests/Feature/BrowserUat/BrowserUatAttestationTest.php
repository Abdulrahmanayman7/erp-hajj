<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Config::set('browser_uat.attestation_enabled', false);
    Config::set('browser_uat.allowed_app_envs', ['local', 'testing']);
});

it('keeps public health free of database identity', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonMissingPath('data.database')
        ->assertJsonMissingPath('data.db');
});

it('returns 404 when attestation is not opted in', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', false);

    $this->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 in production even if the opt-in flag is true', function (): void {
    Config::set('app.env', 'production');
    Config::set('browser_uat.attestation_enabled', true);

    $this->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 for disallowed app environments', function (): void {
    Config::set('app.env', 'staging');
    Config::set('browser_uat.attestation_enabled', true);

    $this->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('attests the actual live connection database name when opted in', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);

    $actual = (string) DB::connection()->getDatabaseName();

    $response = $this->getJson('/api/v1/browser-uat/attestation');

    $response->assertOk()
        ->assertJsonPath('data.attestation', 'browser_uat')
        ->assertJsonPath('data.app_env', 'testing')
        ->assertJsonPath('data.database', $actual)
        ->assertJsonMissingPath('data.host')
        ->assertJsonMissingPath('data.username')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.url');
});

it('exposes the live identity so a spoofed UAT_DATABASE cannot authorize a different database', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);

    $attested = (string) $this->getJson('/api/v1/browser-uat/attestation')->json('data.database');
    $live = (string) DB::connection()->getDatabaseName();
    $spoofedEnv = 'erp_hajj_s11_uat';

    // API always returns the process connection identity — never the caller-supplied env name.
    expect($attested)->toBe($live)
        ->and($attested)->not->toBe($spoofedEnv);

    // Harness contract (mirrors docs/audits/s11-p4-browser-uat/uat-attestation.mjs):
    // UAT_DATABASE is only an expected-value check; a non-disposable attested name fails closed.
    $disposable = (bool) preg_match('/^erp_hajj_.+_uat$/', $attested);
    expect($disposable)->toBeFalse()
        ->and($attested === $spoofedEnv)->toBeFalse();
});
