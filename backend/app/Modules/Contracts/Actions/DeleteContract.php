<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractStatusTransition;
use App\Modules\Contracts\Support\ContractReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteContract
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContractReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Contract $contract, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $contract, $request, $tenant): void {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDeletableDraft($locked);

            $snapshot = [
                'id' => $locked->id,
                'contract_number' => $locked->contract_number,
                'title' => $locked->title,
            ];

            ContractStatusTransition::query()->where('contract_id', $locked->id)->delete();
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract' => $snapshot,
            ], $request);
        });
    }
}
