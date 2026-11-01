<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseApprovalMatrixLevel
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        PurchaseApprovalMatrixLevel $level,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $matrix, $level, $request, $tenant): void {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            /** @var PurchaseApprovalMatrixLevel $row */
            $row = PurchaseApprovalMatrixLevel::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();
            if ((int) $row->matrix_id !== (int) $locked->id) {
                throw SupplierDomainException::matrixNotDraft();
            }

            $rowId = $row->id;
            $order = $row->level_order;
            $row->delete();

            $this->security->record(AuthorizationSecurityEvent::MATRIX_LEVEL_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix_level',
                'entity_id' => $rowId,
                'matrix_id' => $locked->id,
                'level_order' => $order,
            ], $request);
        });
    }
}
