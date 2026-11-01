<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use Illuminate\Support\Collection;

final class ListSupplierEvaluationAppeals
{
    /**
     * @return Collection<int, SupplierEvaluationAppeal>
     */
    public function execute(SupplierEvaluation $evaluation): Collection
    {
        return SupplierEvaluationAppeal::query()
            ->where('evaluation_id', $evaluation->id)
            ->with(['filer', 'reviewer', 'resultingEvaluation'])
            ->orderByDesc('id')
            ->get();
    }
}
