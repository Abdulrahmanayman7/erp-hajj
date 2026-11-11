<?php

use App\Core\BrowserUat\BrowserUatAttestationController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Config::set('browser_uat.attestation_enabled', false);
    Config::set('browser_uat.attestation_token', '');
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
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'test-uat-token-value'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 in production even if the opt-in flag and token are set', function (): void {
    Config::set('app.env', 'production');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'test-uat-token-value'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 for staging even with a valid token', function (): void {
    Config::set('app.env', 'staging');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'test-uat-token-value'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 when the shared token is missing', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $this->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 when the shared token is wrong', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'wrong-token'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('returns 404 when enabled but the configured token is empty', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', '');

    $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'anything'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->assertNotFound()
        ->assertJsonPath('code', 'BROWSER_UAT_ATTESTATION_UNAVAILABLE');
});

it('attests the live database with a valid token and Cache-Control no-store', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $actual = (string) DB::connection()->getDatabaseName();

    $response = $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'test-uat-token-value'])
        ->getJson('/api/v1/browser-uat/attestation');

    $response->assertOk()
        ->assertJsonPath('data.attestation', 'browser_uat')
        ->assertJsonPath('data.app_env', 'testing')
        ->assertJsonPath('data.database', $actual)
        ->assertJsonMissingPath('data.host')
        ->assertJsonMissingPath('data.username')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.url')
        ->assertJsonMissingPath('data.token');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->getContent())->not->toContain('test-uat-token-value');
});

it('exposes the live identity so a spoofed UAT_DATABASE cannot authorize a different database', function (): void {
    Config::set('app.env', 'testing');
    Config::set('browser_uat.attestation_enabled', true);
    Config::set('browser_uat.attestation_token', 'test-uat-token-value');

    $attested = (string) $this->withHeaders([BrowserUatAttestationController::TOKEN_HEADER => 'test-uat-token-value'])
        ->getJson('/api/v1/browser-uat/attestation')
        ->json('data.database');
    $live = (string) DB::connection()->getDatabaseName();
    $spoofedEnv = 'erp_hajj_s11_uat';

    expect($attested)->toBe($live)
        ->and($attested)->not->toBe($spoofedEnv);

    $disposable = (bool) preg_match('/^erp_hajj_.+_uat$/', $attested);
    expect($disposable)->toBeFalse()
        ->and($attested === $spoofedEnv)->toBeFalse();
});
