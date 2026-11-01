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
use App\Modules\SuppliersAndProcurement\Support\SupplierDocumentRecordReferences;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSupplierDocumentRecord
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierDocumentRecordReferences $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Supplier $supplier, array $data, Request $request): SupplierDocumentRecord
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $data, $request, $tenant): SupplierDocumentRecord {
            $lockedSupplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            $def = $this->references->resolveDef($lockedSupplier, (int) $data['requirement_def_id']);
            $document = $this->references->resolveDocument($lockedSupplier, (int) $data['document_id']);
            $this->references->assertNoCurrentRecord($lockedSupplier, $def->requirement_key);

            $record = new SupplierDocumentRecord([
                'supplier_id' => $lockedSupplier->id,
                'requirement_def_id' => $def->id,
                'document_id' => $document->id,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            $record->forceFill([
                'verification_status' => DocumentVerificationStatus::PendingVerification,
                'uploaded_by' => $actor->id,
            ]);

            try {
                // M2 backstop: UNIQUE(tenant, supplier, requirement_key, current_slot).
                $record->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::documentRecordExists();
            }

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_DOCUMENT_RECORD_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_document_record',
                'entity_id' => $record->id,
                'supplier_id' => $lockedSupplier->id,
                'requirement_key' => $def->requirement_key,
                'linked_document_id' => $document->id,
                'expires_at' => $record->expires_at?->format('Y-m-d'),
            ], $request);

            return $record->load(['requirementDef', 'document', 'uploader', 'verifier']);
        });
    }
}
