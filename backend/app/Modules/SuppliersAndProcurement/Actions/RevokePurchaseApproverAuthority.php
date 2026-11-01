<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RevokePurchaseApproverAuthority
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        PurchaseApproverAuthority $authority,
        Request $request,
    ): PurchaseApproverAuthority {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $authority, $request, $tenant): PurchaseApproverAuthority {
            /** @var PurchaseApproverAuthority $locked */
            $locked = PurchaseApproverAuthority::query()->whereKey($authority->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRevoked()) {
                throw SupplierDomainException::authorityAlreadyRevoked();
            }

            $locked->forceFill(['revoked_at' => now()])->save();

            $this->security->record(AuthorizationSecurityEvent::AUTHORITY_REVOKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approver_authority',
                'entity_id' => $locked->id,
                'user_id' => $locked->user_id,
                'revoked_at' => $locked->revoked_at?->toIso8601String(),
            ], $request);

            return $locked->fresh(['user', 'organizationUnit', 'creator']) ?? $locked;
        });
    }
}