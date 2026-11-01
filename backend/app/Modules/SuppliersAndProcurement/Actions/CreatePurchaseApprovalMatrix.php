<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseApprovalMatrix
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string}  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseApprovalMatrix
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): PurchaseApprovalMatrix {
            $matrix = new PurchaseApprovalMatrix([
                'name' => (string) $data['name'],
            ]);
            $matrix->forceFill([
                'status' => PurchaseApprovalMatrixStatus::Draft,
                'version_no' => 0,
                'created_by' => $actor->id,
            ]);
            $matrix->save();

            $this->security->record(AuthorizationSecurityEvent::MATRIX_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix',
                'entity_id' => $matrix->id,
                'status' => $matrix->status->value,
                'version_no' => $matrix->version_no,
            ], $request);

            return $matrix->fresh(['rules', 'levels', 'creator']) ?? $matrix;
        });
    }
}
