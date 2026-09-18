<?php

namespace App\Modules\Authorization\Resources;

use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public bool $withPermissions = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        $payload = [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
            'users_count' => (int) ($role->users_count ?? 0),
            'permissions_count' => (int) ($role->permissions_count ?? 0),
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ];

        if ($this->withPermissions || $role->relationLoaded('permissions')) {
            $payload['permissions'] = $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->sort()->values()->all()
                : [];
        }

        return $payload;
    }
}
