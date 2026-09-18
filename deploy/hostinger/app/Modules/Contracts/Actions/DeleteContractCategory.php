<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteContractCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, ContractCategory $category, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $category, $request, $tenant): void {
            $locked = ContractCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (Contract::query()->where('contract_category_id', $locked->id)->exists()) {
                throw ContractDomainException::categoryInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
                'code' => $locked->code,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_CATEGORY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_category' => $snapshot,
            ], $request);
        });
    }
}
