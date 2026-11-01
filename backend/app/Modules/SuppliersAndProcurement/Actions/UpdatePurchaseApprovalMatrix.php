<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseApprovalMatrix
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string}  $data
     */
    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        array $data,
        Request $request,
    ): PurchaseApprovalMatrix {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $matrix, $data, $request, $tenant): PurchaseApprovalMatrix {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            $locked->fill(['name' => (string) $data['name']]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::MATRIX_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix',
                'entity_id' => $locked->id,
                'status' => $locked->status->value,
                'version_no' => $locked->version_no,
            ], $request);

            return $locked->fresh(['rules', 'levels', 'creator']) ?? $locked;
        });
    }
}
