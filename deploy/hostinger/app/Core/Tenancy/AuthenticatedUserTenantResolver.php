<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * MVP resolution flow: authenticated user → read user.tenant_id → load the
 * tenant fresh (so suspension takes effect immediately) → hand to the
 * middleware for status validation and context initialization.
 *
 * tenant_id is NEVER accepted from a payload or public header; tenant_code
 * is never trusted for authorization.
 */
class AuthenticatedUserTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        if ($user === null || $user->tenant_id === null) {
            return null; // Guest route or platform user — no tenant context.
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant === null) {
            Log::critical('User references a missing tenant row.', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            throw new InvalidTenantContextException;
        }

        return $tenant;
    }
}
