<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use Illuminate\Database\Eloquent\Collection;

final class ListSupplierDocumentRecords
{
    /**
     * Full history including superseded rows, newest first.
     *
     * @return Collection<int, SupplierDocumentRecord>
     */
    public function execute(Supplier $supplier): Collection
    {
        return SupplierDocumentRecord::query()
            ->where('supplier_id', $supplier->id)
            ->with(['requirementDef', 'document', 'uploader', 'verifier'])
            ->orderByDesc('id')
            ->get();
    }
}
