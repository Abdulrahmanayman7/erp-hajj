<?php

it('returns the standardized success envelope from /api/v1/health', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'ERP Hajj API is running',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['application', 'version', 'status', 'timestamp'],
        ])
        ->assertJsonMissingPath('data.environment');

    expect($response->json('data.version'))->toBe('v1');
    expect($response->json('data.status'))->toBe('ok');
});

it('is publicly accessible without authentication', function (): void {
    $this->getJson('/api/v1/health')->assertOk();
});
