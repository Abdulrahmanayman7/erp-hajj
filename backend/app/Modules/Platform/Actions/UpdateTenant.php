<?php

namespace App\Modules\Platform\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTenant
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    /**
     * @param  array{
     *   name?: string,
     *   locale?: string,
     *   timezone?: string,
     *   contact_name?: string|null,
     *   contact_email?: string|null,
     *   contact_phone?: string|null,
     *   notes?: string|null
     * }  $data
     */
    public function execute(User $actor, Tenant $tenant, array $data, Request $request): Tenant
    {
        return DB::transaction(function () use ($actor, $tenant, $data, $request): Tenant {
            /** @var Tenant $locked */
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $before = [
                'name' => $locked->name,
                'locale' => $locked->locale,
                'timezone' => $locked->timezone,
                'contact_name' => $locked->contact_name,
                'contact_email' => $locked->contact_email,
                'contact_phone' => $locked->contact_phone,
                'notes' => $locked->notes,
            ];

            $fill = [];
            foreach (['name', 'locale', 'timezone', 'contact_name', 'contact_email', 'contact_phone', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fill[$field] = $data[$field];
                }
            }

            if ($fill !== []) {
                $locked->forceFill($fill)->save();
            }

            $after = [
                'name' => $locked->name,
                'locale' => $locked->locale,
                'timezone' => $locked->timezone,
                'contact_name' => $locked->contact_name,
                'contact_email' => $locked->contact_email,
                'contact_phone' => $locked->contact_phone,
                'notes' => $locked->notes,
            ];

            $this->security->record(AuthorizationSecurityEvent::TENANT_UPDATED, [
                'context_type' => 'platform',
                'tenant_id' => $locked->id,
                'actor_id' => $actor->id,
                'entity_type' => 'tenant',
                'entity_id' => $locked->id,
                'entity_number' => $locked->tenant_code,
                'entity_label' => $locked->name,
                'before_values' => $before,
                'after_values' => $after,
            ], $request);

            return $locked->fresh();
        });
    }
}
