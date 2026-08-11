<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivateContractCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, ContractCategory $category, Request $request): ContractCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $request, $tenant): ContractCategory {
            $locked = ContractCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $locked->is_active = false;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_CATEGORY_DEACTIVATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_category_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }
}
