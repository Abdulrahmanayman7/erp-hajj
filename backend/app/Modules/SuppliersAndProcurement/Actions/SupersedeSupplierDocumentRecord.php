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
use Illuminate\Validation\ValidationException;

/**
 * Replacement keeps full history: the prior record (and its immutable Document)
 * stays; only its eligibility status becomes `superseded`.
 */
final class SupersedeSupplierDocumentRecord
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierDocumentRecordReferences $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Supplier $supplier, SupplierDocumentRecord $prior, array $data, Request $request): SupplierDocumentRecord
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $prior, $data, $request, $tenant): SupplierDocumentRecord {
            $lockedSupplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $lockedPrior = SupplierDocumentRecord::query()
                ->whereKey($prior->id)
                ->where('supplier_id', $lockedSupplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPrior->verification_status->isSupersedable()) {
                throw SupplierDomainException::documentInvalidState();
            }

            $priorKey = (string) $lockedPrior->requirement_key;
            $def = isset($data['requirement_def_id'])
                ? $this->references->resolveDef($lockedSupplier, (int) $data['requirement_def_id'])
                : $this->references->resolveDef($lockedSupplier, (int) $lockedPrior->requirement_def_id);

            if ($def->requirement_key !== $priorKey) {
                throw ValidationException::withMessages([
                    'requirement_def_id' => ['يجب أن يحمل المتطلب البديل مفتاح المتطلب نفسه.'],
                ]);
            }

            $document = $this->references->resolveDocument($lockedSupplier, (int) $data['document_id']);

            $lockedPrior->forceFill(['verification_status' => DocumentVerificationStatus::Superseded])->save();

            $record = new SupplierDocumentRecord([
                'supplier_id' => $lockedSupplier->id,
                'requirement_def_id' => $def->id,
                'document_id' => $document->id,
                'expires_at' => $data['expires_at'] ?? null,
                'supersedes_id' => $lockedPrior->id,
            ]);
            $record->forceFill([
                'verification_status' => DocumentVerificationStatus::PendingVerification,
                'uploaded_by' => $actor->id,
            ]);

            try {
                // The prior row already released the current slot (superseded => slot = id).
                $record->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::documentRecordExists();
            }

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_DOCUMENT_RECORD_SUPERSEDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_document_record',
                'entity_id' => $record->id,
                'supplier_id' => $lockedSupplier->id,
                'requirement_key' => $priorKey,
                'superseded_record_id' => $lockedPrior->id,
                'new_record_id' => $record->id,
                'linked_document_id' => $document->id,
            ], $request);

            return $record->load(['requirementDef', 'document', 'uploader', 'verifier']);
        });
    }
}
