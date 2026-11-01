<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RejectSupplierDocumentRecord
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Supplier $supplier, SupplierDocumentRecord $record, string $rejectionReason, Request $request): SupplierDocumentRecord
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $supplier, $record, $rejectionReason, $request, $tenant): SupplierDocumentRecord {
                $locked = SupplierDocumentRecord::query()
                    ->whereKey($record->id)
                    ->where('supplier_id', $supplier->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->verification_status !== DocumentVerificationStatus::PendingVerification) {
                    throw SupplierDomainException::documentInvalidState();
                }

                // C3: the uploader can never review their own document.
                if ((int) $locked->uploaded_by === (int) $actor->id) {
                    throw SupplierDomainException::sodViolation();
                }

                $locked->forceFill([
                    'verification_status' => DocumentVerificationStatus::Rejected,
                    'rejection_reason' => $rejectionReason,
                ])->save();

                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_DOCUMENT_RECORD_REJECTED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_document_record',
                    'entity_id' => $locked->id,
                    'supplier_id' => $supplier->id,
                    'rejection_reason' => $rejectionReason,
                ], $request);

                return $locked->load(['requirementDef', 'document', 'uploader', 'verifier']);
            });
        } catch (SupplierDomainException $e) {
            if ($e->errorCode() === 'SUPPLIER_SOD_VIOLATION') {
                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_document_record',
                    'entity_id' => $record->id,
                    'supplier_id' => $supplier->id,
                    'attempted_action' => 'reject_document_record',
                ], $request);
            }

            throw $e;
        }
    }
}
