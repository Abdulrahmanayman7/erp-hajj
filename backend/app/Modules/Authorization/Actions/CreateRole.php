<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CreateRole
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, code?: string|null, description?: string|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): Role
    {
        $tenant = $this->tenantContext->require();
        $code = $data['code'] ?? null;
        if (! is_string($code) || $code === '') {
            $code = Str::slug($data['name'], '_');
            $code = preg_replace('/[^a-z0-9_]/', '', Str::lower($code)) ?: 'role';
            if (! preg_match('/^[a-z]/', $code)) {
                $code = 'r_'.$code;
            }
        }

        $role = Role::query()->create([
            'name' => $data['name'],
            'code' => $code,
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        $this->security->record(AuthorizationSecurityEvent::ROLE_CREATED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'role_id' => $role->id,
            'code' => $role->code,
        ], $request);

        $role->loadCount(['users', 'permissions']);

        return $role;
    }
}
