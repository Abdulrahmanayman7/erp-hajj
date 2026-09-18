<?php

namespace App\Core\Auth\Actions;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Support\AuthSecurity;
use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Exceptions\TenantNotActiveException;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Loads the current user for /auth/me and enforces tenant lifecycle for
 * tenant users without applying EnsureTenantIsActive (platform users have
 * no TenantContext and must still reach this endpoint).
 */
class GetAuthenticatedUser
{
    public function __construct(private readonly AuthSecurity $security) {}

    public function execute(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isPlatformUser()) {
            return $user;
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant === null) {
            $this->security->record(AuthSecurityEvent::LOGIN_FAILED, $request, [
                'user_id' => $user->id,
                'reason' => 'tenant_context_invalid',
            ]);

            throw new InvalidTenantContextException;
        }

        if (! $tenant->status->isActive()) {
            $this->security->record(AuthSecurityEvent::TENANT_BLOCKED_ACCESS_ATTEMPT, $request, [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'tenant_status' => $tenant->status->value,
            ]);

            throw new TenantNotActiveException($tenant->status);
        }

        $user->setRelation('tenant', $tenant);

        return $user;
    }
}
