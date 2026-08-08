<?php

namespace App\Core\Auth\Actions;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Support\AuthRateLimiter;
use App\Core\Auth\Support\AuthSecurity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPassword
{
    public function __construct(
        private readonly AuthRateLimiter $limiter,
        private readonly AuthSecurity $security,
    ) {}

    /**
     * Always succeeds outwardly after validation + rate limit.
     * Does not reveal whether the email exists or tenant membership.
     */
    public function execute(Request $request, string $email): void
    {
        $key = 'forgot:'.AuthRateLimiter::key($email, (string) $request->ip());
        $this->limiter->ensureAccepted($key);
        $this->limiter->hit($key);

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            Password::broker()->sendResetLink(['email' => $email]);

            $this->security->record(AuthSecurityEvent::PASSWORD_RESET_REQUESTED, $request, [
                'user_id' => $user->id,
                // No email plaintext in context — avoid log side channels.
            ]);
        } else {
            // Same audit event name without asserting existence via user_id.
            $this->security->record(AuthSecurityEvent::PASSWORD_RESET_REQUESTED, $request, [
                'requested' => true,
            ]);
        }
    }
}
