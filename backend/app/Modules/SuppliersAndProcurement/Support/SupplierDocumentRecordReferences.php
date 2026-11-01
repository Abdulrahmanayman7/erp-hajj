<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-scoped reference resolution for supplier document records.
 * Unknown / cross-tenant ids surface as 404 (ModelNotFoundException).
 */
final class SupplierDocumentRecordReferences
{
    public function resolveDef(Supplier $supplier, int $defId): SupplierDocumentRequirementDef
    {
        $def = SupplierDocumentRequirementDef::query()
            ->with('requirementSet')
            ->findOrFail($defId);

        $applicable = $def->requirementSet?->isApproved() === true
            && $def->supplier_type === $supplier->supplier_type
            && ($def->country_code === null || $def->country_code === strtoupper((string) $supplier->country_code))
            && ($def->supplier_category_id === null
                || $supplier->categories()->where('supplier_categories.id', $def->supplier_category_id)->exists());

        if (! $applicable) {
            throw ValidationException::withMessages([
                'requirement_def_id' => ['المتطلب غير معتمد أو غير قابل للتطبيق على هذا المورد.'],
            ]);
        }

        return $def;
    }

    public function resolveDocument(Supplier $supplier, int $documentId): Document
    {
        $document = Document::query()->findOrFail($documentId);

        // H1: the evidence Document must be active and already linked to THIS supplier;
        // unlinked, archived or other-entity documents can never back a record.
        $linkedHere = $document->linkable_type === DocumentLinkableType::Supplier->value
            && (int) $document->linkable_id === (int) $supplier->id;

        if (! $linkedHere || ! $document->status->isActive()) {
            throw ValidationException::withMessages([
                'document_id' => ['المستند غير صالح لهذا المورد (يجب أن يكون نشطاً ومرتبطاً بهذا المورد).'],
            ]);
        }

        return $document;
    }

    public function assertNoCurrentRecord(Supplier $supplier, string $requirementKey): void
    {
        $exists = SupplierDocumentRecord::query()
            ->where('supplier_id', $supplier->id)
            ->where('requirement_key', $requirementKey)
            ->where('verification_status', '!=', DocumentVerificationStatus::Superseded->value)
            ->exists();

        if ($exists) {
            throw SupplierDomainException::documentRecordExists();
        }
    }
}
