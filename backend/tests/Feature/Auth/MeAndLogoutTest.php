<?php

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('me returns tenant user profile', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_platform_user', false)
        ->assertJsonPath('data.tenant.id', $tenant->id)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.permissions');
});

test('me returns platform user profile', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.is_platform_user', true)
        ->assertJsonPath('data.tenant', null);
});

test('me unauthenticated returns AUTH_UNAUTHENTICATED', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'AUTH_UNAUTHENTICATED');
});

test('me rejects disabled account', function (): void {
    $user = User::factory()->disabled()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTH_ACCOUNT_DISABLED');
});

test('me rejects suspended tenant', function (): void {
    $tenant = Tenant::factory()->suspended()->create();
    $user = User::factory()->forTenant($tenant)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});

test('logout invalidates the session', function (): void {
    Event::fake([AuthSecurityEvent::class]);

    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $headers = [
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ];

    $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ])->assertOk();

    $this->withHeaders($headers)->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->withHeaders($headers)->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'AUTH_UNAUTHENTICATED');

    Event::assertDispatched(AuthSecurityEvent::class, fn (AuthSecurityEvent $e): bool => $e->name === AuthSecurityEvent::LOGOUT);
});

test('protected request is blocked after tenant suspension', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $headers = [
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ];

    $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ])->assertOk();

    $tenant->update(['status' => TenantStatus::Suspended]);

    $this->withHeaders($headers)->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});
