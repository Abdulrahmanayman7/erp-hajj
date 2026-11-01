<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpsertPurchaseApprovalMatrixLevel
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{level_order?: int, name?: string}  $data
     */
    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        array $data,
        Request $request,
        ?PurchaseApprovalMatrixLevel $existing = null,
    ): PurchaseApprovalMatrixLevel {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $matrix, $data, $request, $existing, $tenant): PurchaseApprovalMatrixLevel {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            if ($existing !== null) {
                /** @var PurchaseApprovalMatrixLevel $level */
                $level = PurchaseApprovalMatrixLevel::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if ((int) $level->matrix_id !== (int) $locked->id) {
                    throw SupplierDomainException::matrixNotDraft();
                }
            } else {
                $level = new PurchaseApprovalMatrixLevel;
                $level->forceFill(['matrix_id' => $locked->id]);
            }

            $payload = [];
            if (array_key_exists('name', $data) || $existing === null) {
                $payload['name'] = (string) $data['name'];
            }
            if (array_key_exists('level_order', $data) || $existing === null) {
                $order = (int) $data['level_order'];
                if ($order < 1) {
                    throw SupplierDomainException::matrixLevelsInvalid();
                }
                $payload['level_order'] = $order;
            }

            $level->fill($payload);

            try {
                $level->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::levelOrderConflict();
            }

            $event = $existing === null
                ? AuthorizationSecurityEvent::MATRIX_LEVEL_CREATED
                : AuthorizationSecurityEvent::MATRIX_LEVEL_UPDATED;

            $this->security->record($event, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix_level',
                'entity_id' => $level->id,
                'matrix_id' => $locked->id,
                'level_order' => $level->level_order,
            ], $request);

            return $level->fresh(['matrix']) ?? $level;
        });
    }
}
