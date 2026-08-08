<?php

namespace App\Core\Auth\Actions;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Exceptions\PasswordResetExpiredException;
use App\Core\Auth\Exceptions\PasswordResetInvalidException;
use App\Core\Auth\Support\AuthRateLimiter;
use App\Core\Auth\Support\AuthSecurity;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPassword
{
    public function __construct(
        private readonly AuthRateLimiter $limiter,
        private readonly AuthSecurity $security,
    ) {}

    public function execute(Request $request, string $email, string $token, string $password): void
    {
        $key = 'reset:'.AuthRateLimiter::key($email, (string) $request->ip());
        $this->limiter->ensureAccepted($key);

        $this->rejectIfExpired($email);

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->revokeOtherSessions($user);

                event(new PasswordResetEvent($user));

                $this->security->record(AuthSecurityEvent::PASSWORD_RESET_COMPLETED, $request, [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                ]);
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->limiter->clear($key);

            return;
        }

        $this->limiter->hit($key);

        throw new PasswordResetInvalidException;
    }

    private function rejectIfExpired(string $email): void
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || $record->created_at === null) {
            return; // Let the broker report invalid.
        }

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        if (now()->subMinutes($expireMinutes)->greaterThan($record->created_at)) {
            throw new PasswordResetExpiredException;
        }
    }

    private function revokeOtherSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
