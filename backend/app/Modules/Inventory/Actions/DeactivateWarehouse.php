<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivateWarehouse
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Warehouse $warehouse, Request $request): Warehouse
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $warehouse, $request, $tenant): Warehouse {
            $locked = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            $locked->is_active = false;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::WAREHOUSE_DEACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'warehouse_id' => $locked->id,
                'warehouse_number' => $locked->warehouse_number,
            ], $request);

            return $locked->load(['organizationUnit', 'responsibleEmployee', 'createdBy']);
        });
    }
}
