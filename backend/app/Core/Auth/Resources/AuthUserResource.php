<?php

namespace App\Core\Auth\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authentication-safe current-user payload — no secrets, no fabricated permissions.
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

        $tenant = $user->relationLoaded('tenant') ? $user->tenant : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'is_platform_user' => $user->isPlatformUser(),
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'tenant_code' => $tenant->tenant_code,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ],
        ];
    }
}
