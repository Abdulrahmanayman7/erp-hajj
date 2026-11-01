<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;

/**
 * Optional banking evidence Document resolution (IIB-D5).
 */
final class SupplierBankEvidence
{
    public function resolveOptional(Supplier $supplier, mixed $documentId): ?int
    {
        if ($documentId === null || $documentId === '') {
            return null;
        }

        $id = (int) $documentId;
        if ($id <= 0) {
            throw SupplierDomainException::bankEvidenceInvalid();
        }

        $document = Document::query()->find($id);
        if ($document === null) {
            throw SupplierDomainException::bankEvidenceInvalid();
        }

        $linkedHere = $document->linkable_type === DocumentLinkableType::Supplier->value
            && (int) $document->linkable_id === (int) $supplier->id;

        if (! $linkedHere || ! $document->status->isActive()) {
            throw SupplierDomainException::bankEvidenceInvalid();
        }

        return (int) $document->id;
    }
}
