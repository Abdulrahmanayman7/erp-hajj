<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RevokePurchaseApprovalDelegation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        PurchaseApprovalDelegation $delegation,
        Request $request,
    ): PurchaseApprovalDelegation {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $delegation, $request, $tenant): PurchaseApprovalDelegation {
            /** @var PurchaseApprovalDelegation $locked */
            $locked = PurchaseApprovalDelegation::query()->whereKey($delegation->id)->lockForUpdate()->firstOrFail();

            $isAdmin = $actor->hasPermission('purchase_requests.manage_delegations');
            if (! $isAdmin && (int) $actor->id !== (int) $locked->from_user_id) {
                throw SupplierDomainException::prDelegationInvalid(
                    'إلغاء التفويض متاح للمفوِّض أو لمن يملك إدارة التفويضات.',
                );
            }

            if ($locked->revoked_at !== null) {
                throw SupplierDomainException::prDelegationInvalid('التفويض ملغى مسبقاً.');
            }

            $locked->forceFill([
                'revoked_at' => now(),
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::DELEGATION_REVOKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_delegation',
                'entity_id' => $locked->id,
                'from_user_id' => $locked->from_user_id,
                'to_user_id' => $locked->to_user_id,
            ], $request);

            return $locked->fresh(['fromUser', 'toUser', 'authorizer']) ?? $locked;
        });
    }
}
