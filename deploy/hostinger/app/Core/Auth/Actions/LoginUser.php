<?php

namespace App\Core\Auth\Actions;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Exceptions\AccountDisabledException;
use App\Core\Auth\Exceptions\InvalidCredentialsException;
use App\Core\Auth\Support\AuthRateLimiter;
use App\Core\Auth\Support\AuthSecurity;
use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Exceptions\TenantNotActiveException;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginUser
{
    public function __construct(
        private readonly AuthRateLimiter $limiter,
        private readonly AuthSecurity $security,
    ) {}

    public function execute(Request $request, string $email, string $password, bool $remember): User
    {
        $key = AuthRateLimiter::key($email, (string) $request->ip());
        $this->limiter->ensureAccepted($key);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->limiter->hit($key);
            $this->security->record(AuthSecurityEvent::LOGIN_FAILED, $request, [
                'reason' => 'invalid_credentials',
            ]);

            throw new InvalidCredentialsException;
        }

        if ($user->status === UserStatus::Disabled) {
            $this->limiter->hit($key);
            $this->security->record(AuthSecurityEvent::ACCOUNT_DISABLED_ACCESS_ATTEMPT, $request, [
                'user_id' => $user->id,
            ]);

            throw new AccountDisabledException;
        }

        if (! $user->isPlatformUser()) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if ($tenant === null) {
                $this->limiter->hit($key);
                $this->security->record(AuthSecurityEvent::LOGIN_FAILED, $request, [
                    'user_id' => $user->id,
                    'reason' => 'tenant_context_invalid',
                ]);

                throw new InvalidTenantContextException;
            }

            if (! $tenant->status->isActive()) {
                $this->limiter->hit($key);
                $this->security->record(AuthSecurityEvent::TENANT_BLOCKED_ACCESS_ATTEMPT, $request, [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'tenant_status' => $tenant->status->value,
                ]);

                throw new TenantNotActiveException($tenant->status);
            }

            $user->setRelation('tenant', $tenant);
        }

        // Establish the session auth, then regenerate the session id (fixation defense).
        // Order matches Laravel's SPA guidance while satisfying "no session auth before gates".
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $this->limiter->clear($key);

        $this->security->record(AuthSecurityEvent::LOGIN_SUCCESS, $request, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'context_type' => $user->isPlatformUser() ? 'platform' : 'tenant',
        ]);

        return $user->loadMissing('tenant');
    }
}
