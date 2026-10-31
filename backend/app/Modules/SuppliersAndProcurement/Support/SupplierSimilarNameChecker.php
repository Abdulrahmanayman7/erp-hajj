<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Models\Supplier;

final class SupplierSimilarNameChecker
{
    /**
     * @return list<array{code: string, message: string, supplier_id: int, supplier_number: string, display_name: string}>
     */
    public function warningsFor(string $displayName, ?int $excludeSupplierId = null): array
    {
        $needle = SupplierDisplayNameNormalizer::normalize($displayName);
        if ($needle === '') {
            return [];
        }

        $query = Supplier::query()->select(['id', 'supplier_number', 'display_name']);
        if ($excludeSupplierId !== null) {
            $query->whereKeyNot($excludeSupplierId);
        }

        $warnings = [];
        foreach ($query->cursor() as $supplier) {
            if (SupplierDisplayNameNormalizer::normalize($supplier->display_name) !== $needle) {
                continue;
            }

            $warnings[] = [
                'code' => 'SUPPLIER_SIMILAR_DISPLAY_NAME',
                'message' => 'يوجد مورد آخر باسم عرض مشابه.',
                'supplier_id' => $supplier->id,
                'supplier_number' => $supplier->supplier_number,
                'display_name' => $supplier->display_name,
            ];
        }

        return $warnings;
    }
}
