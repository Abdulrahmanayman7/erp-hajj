<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseApprovalMatrixRule
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        PurchaseApprovalMatrixRule $rule,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $matrix, $rule, $request, $tenant): void {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            /** @var PurchaseApprovalMatrixRule $line */
            $line = PurchaseApprovalMatrixRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if ((int) $line->matrix_id !== (int) $locked->id) {
                throw SupplierDomainException::matrixNotDraft();
            }

            $lineId = $line->id;
            $line->delete();

            $this->security->record(AuthorizationSecurityEvent::MATRIX_RULE_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix_rule',
                'entity_id' => $lineId,
                'matrix_id' => $locked->id,
            ], $request);
        });
    }
}
