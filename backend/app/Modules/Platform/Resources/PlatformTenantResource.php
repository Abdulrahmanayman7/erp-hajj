<?php

namespace App\Modules\Platform\Resources;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class PlatformTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        $owner = $tenant->getAttribute('owner_summary');

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'code' => $tenant->tenant_code,
            'tenant_code' => $tenant->tenant_code,
            'status' => $tenant->status->value,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
            'contact' => [
                'name' => $tenant->contact_name,
                'email' => $tenant->contact_email,
                'phone' => $tenant->contact_phone,
            ],
            'notes' => $tenant->notes,
            'owner' => $owner,
            'users_count' => (int) ($tenant->users_count ?? $tenant->users()->count()),
            'suspended_at' => $tenant->suspended_at?->toIso8601String(),
            'archived_at' => $tenant->archived_at?->toIso8601String(),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
        ];
    }
}
