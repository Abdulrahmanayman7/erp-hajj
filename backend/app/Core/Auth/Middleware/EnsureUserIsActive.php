<?php

namespace App\Core\Auth\Middleware;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Exceptions\AccountDisabledException;
use App\Core\Auth\Support\AuthSecurity;
use App\Core\Auth\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects disabled accounts on protected requests after Sanctum authentication.
 */
class EnsureUserIsActive
{
    public function __construct(private readonly AuthSecurity $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $user->status === UserStatus::Disabled) {
            $this->security->record(AuthSecurityEvent::ACCOUNT_DISABLED_ACCESS_ATTEMPT, $request, [
                'user_id' => $user->id,
            ]);

            throw new AccountDisabledException;
        }

        return $next($request);
    }
}
