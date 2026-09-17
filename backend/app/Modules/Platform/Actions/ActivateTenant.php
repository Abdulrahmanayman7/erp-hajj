<?php

namespace App\Modules\Platform\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Platform\Exceptions\PlatformDomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivateTenant
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    public function execute(User $actor, Tenant $tenant, Request $request): Tenant
    {
        return DB::transaction(function () use ($actor, $tenant, $request): Tenant {
            /** @var Tenant $locked */
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo(TenantStatus::Active)) {
                throw PlatformDomainException::invalidTenantTransition();
            }

            $locked->forceFill([
                'status' => TenantStatus::Active,
                'suspended_at' => null,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::TENANT_ACTIVATED, [
                'context_type' => 'platform',
                'tenant_id' => $locked->id,
                'actor_id' => $actor->id,
                'entity_type' => 'tenant',
                'entity_id' => $locked->id,
                'entity_number' => $locked->tenant_code,
                'entity_label' => $locked->name,
                'from_status' => $from->value,
                'to_status' => TenantStatus::Active->value,
            ], $request);

            return $locked->fresh();
        });
    }
}
