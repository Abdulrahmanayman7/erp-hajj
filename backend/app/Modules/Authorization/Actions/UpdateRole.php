<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;

final class UpdateRole
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    /**
     * @param  array{name?: string, description?: string|null}  $data
     */
    public function execute(User $actor, Role $role, array $data, Request $request): Role
    {
        $before = [
            'name' => $role->name,
            'description' => $role->description,
        ];

        $role->fill(array_intersect_key($data, array_flip(['name', 'description'])));
        $role->save();

        $this->security->record(AuthorizationSecurityEvent::ROLE_UPDATED, [
            'tenant_id' => $role->tenant_id,
            'actor_id' => $actor->id,
            'role_id' => $role->id,
            'before' => $before,
            'after' => [
                'name' => $role->name,
                'description' => $role->description,
            ],
        ], $request);

        return $role->loadCount(['users', 'permissions']);
    }
}
