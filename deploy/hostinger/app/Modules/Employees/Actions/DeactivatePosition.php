<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivatePosition
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Position $position, Request $request): Position
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $position, $request, $tenant): Position {
            $locked = Position::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();
            $locked->is_active = false;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::POSITION_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'position_id' => $locked->id,
                'action' => 'deactivated',
            ], $request);

            return $locked;
        });
    }
}
