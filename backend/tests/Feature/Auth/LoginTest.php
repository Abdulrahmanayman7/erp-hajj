<?php

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    RateLimiter::clear('user@example.com|127.0.0.1');
});

function loginPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'user@example.com',
        'password' => 'password',
        'remember' => false,
    ], $overrides);
}

test('valid tenant user can log in', function (): void {
    Event::fake([AuthSecurityEvent::class]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.is_platform_user', false)
        ->assertJsonPath('data.tenant.id', $tenant->id)
        ->assertJsonPath('data.tenant.tenant_code', $tenant->tenant_code);

    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(AuthSecurityEvent::class, fn (AuthSecurityEvent $e): bool => $e->name === AuthSecurityEvent::LOGIN_SUCCESS);
});

test('valid platform user can log in', function (): void {
    $user = User::factory()->create([
        'email' => 'platform@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload([
        'email' => 'platform@example.com',
        'password' => 'password123',
    ]))
        ->assertOk()
        ->assertJsonPath('data.is_platform_user', true)
        ->assertJsonPath('data.tenant', null);

    $this->assertAuthenticatedAs($user);
});

test('invalid email format returns 422', function (): void {
    spaPostJson('/api/v1/auth/login', loginPayload(['email' => 'not-an-email']))
        ->assertStatus(422);
});

test('wrong password returns AUTH_INVALID_CREDENTIALS', function (): void {
    User::factory()->create(['email' => 'user@example.com']);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong-pass']))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'AUTH_INVALID_CREDENTIALS');
});

test('unknown email is indistinguishable from wrong password', function (): void {
    User::factory()->create(['email' => 'user@example.com']);

    $wrong = spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong-pass']));
    $unknown = spaPostJson('/api/v1/auth/login', loginPayload(['email' => 'nobody@example.com']));

    expect($wrong->status())->toBe(401)
        ->and($unknown->status())->toBe(401)
        ->and($wrong->json('code'))->toBe('AUTH_INVALID_CREDENTIALS')
        ->and($unknown->json('code'))->toBe('AUTH_INVALID_CREDENTIALS')
        ->and($wrong->json('message'))->toBe($unknown->json('message'));
});

test('email lookup is case-normalized', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'mixed.case@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload([
        'email' => 'Mixed.Case@Example.com',
        'password' => 'password123',
    ]))->assertOk();
});

test('login rate limit returns AUTH_TOO_MANY_ATTEMPTS', function (): void {
    User::factory()->create(['email' => 'user@example.com']);

    for ($i = 0; $i < 5; $i++) {
        spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']));
    }

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']))
        ->assertStatus(429)
        ->assertJsonPath('code', 'AUTH_TOO_MANY_ATTEMPTS');
});

test('successful login clears the rate limiter', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    for ($i = 0; $i < 4; $i++) {
        spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']));
    }

    spaPostJson('/api/v1/auth/login', loginPayload([
        'password' => 'password123',
    ]))->assertOk();

    // After clear, failures start counting again from zero.
    for ($i = 0; $i < 5; $i++) {
        spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']));
    }

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']))
        ->assertStatus(429);
});

test('session is regenerated on login', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->withSession(['_token' => 'old-token-value']);
    $before = session()->getId();

    spaPostJson('/api/v1/auth/login', loginPayload([
        'password' => 'password123',
    ]))->assertOk();

    expect(session()->getId())->not->toBe($before);
});

test('remember me is accepted', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload([
        'password' => 'password123',
        'remember' => true,
    ]))->assertOk();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('disabled user cannot log in', function (): void {
    Event::fake([AuthSecurityEvent::class]);

    User::factory()->disabled()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload([
        'password' => 'password123',
    ]))
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTH_ACCOUNT_DISABLED');

    $this->assertGuest();
    Event::assertDispatched(AuthSecurityEvent::class, fn (AuthSecurityEvent $e): bool => $e->name === AuthSecurityEvent::ACCOUNT_DISABLED_ACCESS_ATTEMPT);
});

test('pending tenant blocks login', function (): void {
    $tenant = Tenant::factory()->pending()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'password123']))
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_PENDING');

    $this->assertGuest();
});

test('suspended tenant blocks login', function (): void {
    $tenant = Tenant::factory()->suspended()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'password123']))
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});

test('archived tenant blocks login', function (): void {
    $tenant = Tenant::factory()->archived()->create();
    User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'password123']))
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_ARCHIVED');
});

test('invalid tenant relationship blocks login', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    // Drop the FK so we can simulate registry corruption in SQLite tests.
    Schema::table('users', function (Blueprint $table): void {
        $table->dropForeign(['tenant_id']);
    });

    DB::table('users')->where('id', $user->id)->update(['tenant_id' => 999999]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'password123']))
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_CONTEXT_INVALID');

    $this->assertGuest();
});

test('no session is created when gates fail', function (): void {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
        'status' => UserStatus::Disabled,
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'password123']));

    $this->assertGuest();
});

test('security events never contain secrets', function (): void {
    Event::fake([AuthSecurityEvent::class]);

    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
    ]);

    spaPostJson('/api/v1/auth/login', loginPayload(['password' => 'wrong']));

    Event::assertDispatched(AuthSecurityEvent::class, function (AuthSecurityEvent $e): bool {
        $json = json_encode($e->context);

        return ! str_contains((string) $json, 'password')
            && ! str_contains((string) $json, 'wrong')
            && ! array_key_exists('password', $e->context)
            && ! array_key_exists('token', $e->context)
            && ! array_key_exists('session_id', $e->context);
    });
});
