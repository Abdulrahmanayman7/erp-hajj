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

it('echoes the requesting SPA origin on CORS preflight', function (string $origin): void {
    $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/health')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
})->with([
    'http://localhost:5173',
    'http://127.0.0.1:5173',
]);

it('does not echo an unknown CORS origin', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'http://evil.example',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://evil.example');
});
