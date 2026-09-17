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

final class ArchiveTenant
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    public function execute(User $actor, Tenant $tenant, string $reason, Request $request): Tenant
    {
        return DB::transaction(function () use ($actor, $tenant, $reason, $request): Tenant {
            /** @var Tenant $locked */
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo(TenantStatus::Archived)) {
                throw PlatformDomainException::invalidTenantTransition();
            }

            $locked->forceFill([
                'status' => TenantStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::TENANT_ARCHIVED, [
                'context_type' => 'platform',
                'tenant_id' => $locked->id,
                'actor_id' => $actor->id,
                'entity_type' => 'tenant',
                'entity_id' => $locked->id,
                'entity_number' => $locked->tenant_code,
                'entity_label' => $locked->name,
                'from_status' => $from->value,
                'to_status' => TenantStatus::Archived->value,
                'reason' => $reason,
            ], $request);

            return $locked->fresh();
        });
    }
}
