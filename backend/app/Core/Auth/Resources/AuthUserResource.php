<?php

namespace App\Core\Auth\Resources;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\EffectivePlatformPermissions;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authentication-safe current-user payload — no secrets.
 * Sprint 006: additive roles + sorted effective permissions.
 * Platform users reuse the same `roles` / `permissions` keys (platform RBAC).
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();
        $effective = app(EffectivePermissions::class);
        $platformEffective = app(EffectivePlatformPermissions::class);

        $roles = [];
        $permissions = [];
        $employeeId = null;

        if ($user->isPlatformUser()) {
            if (! $user->relationLoaded('platformRoles')) {
                $user->load(['platformRoles' => fn ($q) => $q->where('platform_roles.is_active', true)]);
            }
            $roles = $platformEffective->activeRolesPayload($user);
            $permissions = $platformEffective->forUser($user);
        } elseif ($user->tenant_id !== null) {
            $tenantModel = $tenant instanceof Tenant
                ? $tenant
                : Tenant::query()->find($user->tenant_id);

            if ($tenantModel !== null) {
                [$roles, $permissions, $employeeId] = app(TenantContext::class)->runAsTenant(
                    $tenantModel,
                    function () use ($user, $effective): array {
                        if (! $user->relationLoaded('roles')) {
                            $user->load(['roles' => fn ($q) => $q->where('roles.is_active', true)]);
                        }

                        $linkedEmployeeId = Employee::query()
                            ->where('user_id', $user->id)
                            ->value('id');

                        return [
                            $effective->activeRolesPayload($user),
                            $effective->forUser($user),
                            $linkedEmployeeId !== null ? (int) $linkedEmployeeId : null,
                        ];
                    },
                );
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'avatar_group' => $user->avatar_group?->value ?? 'neutral',
            'is_platform_user' => $user->isPlatformUser(),
            'employee_id' => $employeeId,
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'tenant_code' => $tenant->tenant_code,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
