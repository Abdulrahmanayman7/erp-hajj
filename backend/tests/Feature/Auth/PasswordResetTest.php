<?php

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear('forgot:user@example.com|127.0.0.1');
    RateLimiter::clear('forgot:nobody@example.com|127.0.0.1');
    RateLimiter::clear('reset:user@example.com|127.0.0.1');
});

test('forgot password for known email sends notification and returns generic success', function (): void {
    Notification::fake();
    Event::fake([AuthSecurityEvent::class]);

    $user = User::factory()->create(['email' => 'user@example.com']);

    spaPostJson('/api/v1/auth/forgot-password', [
        'email' => 'user@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
    Event::assertDispatched(AuthSecurityEvent::class, fn (AuthSecurityEvent $e): bool => $e->name === AuthSecurityEvent::PASSWORD_RESET_REQUESTED);
});

test('forgot password for unknown email is outwardly identical', function (): void {
    Notification::fake();

    $knownUser = User::factory()->create(['email' => 'user@example.com']);

    $known = spaPostJson('/api/v1/auth/forgot-password', ['email' => 'user@example.com'])->assertOk();
    $unknown = spaPostJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    expect($known->json('success'))->toBe($unknown->json('success'))
        ->and($known->json('message'))->toBe($unknown->json('message'))
        ->and($known->json('data'))->toBe($unknown->json('data'));

    Notification::assertSentTo($knownUser, ResetPassword::class);
    Notification::assertCount(1);
});

test('forgot password is rate limited', function (): void {
    for ($i = 0; $i < 5; $i++) {
        spaPostJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);
    }

    spaPostJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'AUTH_TOO_MANY_ATTEMPTS');
});

test('valid reset updates password and invalidates token', function (): void {
    Event::fake([AuthSecurityEvent::class]);
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'user@example.com',
        'password' => Hash::make('OldPassword1'),
    ]);

    $token = Password::broker()->createToken($user);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertOk();

    expect(Hash::check('NewPassword1', $user->fresh()->password))->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBeFalse();

    Event::assertDispatched(AuthSecurityEvent::class, fn (AuthSecurityEvent $e): bool => $e->name === AuthSecurityEvent::PASSWORD_RESET_COMPLETED
        && ! array_key_exists('token', $e->context)
        && ! array_key_exists('password', $e->context));
});

test('invalid reset token returns AUTH_PASSWORD_RESET_INVALID', function (): void {
    User::factory()->create(['email' => 'user@example.com']);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => 'invalid-token',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'AUTH_PASSWORD_RESET_INVALID');
});

test('expired reset token returns AUTH_PASSWORD_RESET_EXPIRED', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subHours(2)]);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'AUTH_PASSWORD_RESET_EXPIRED');
});

test('password policy is enforced on reset', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'lettersOnly',
        'password_confirmation' => 'lettersOnly',
    ])->assertStatus(422);
});

test('password confirmation mismatch fails validation', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'OtherPassword1',
    ])->assertStatus(422);
});

test('reset revokes database sessions for the user', function (): void {
    config(['session.driver' => 'database']);

    $user = User::factory()->create(['email' => 'user@example.com']);

    DB::table('sessions')->insert([
        'id' => 'session-abc',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $token = Password::broker()->createToken($user);

    spaPostJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('password reset email html is rtl without changing arabic copy', function (): void {
    $user = User::factory()->create([
        'name' => 'مستخدم الاختبار',
        'email' => 'rtl-mail@example.com',
    ]);

    $mail = (new ResetPassword('test-token'))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('تعيين كلمة المرور — رفيع');
    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('direction: rtl')
        ->toContain('مرحبًا '.$user->name)
        ->toContain('تم إنشاء حسابك أو طلب إعادة تعيين كلمة المرور في منصة رفيع.')
        ->toContain('اضغط الزر أدناه لتعيين كلمة مرور جديدة.')
        ->toContain('تعيين كلمة المرور')
        ->toContain('رابط التعيين صالح لمدة 60 دقيقة.')
        ->toContain('إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.')
        ->toContain('مع التحية، فريق رفيع');
});
