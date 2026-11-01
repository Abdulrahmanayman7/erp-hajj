<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\EligibilityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierEligibilityController
{
    use AuthorizesRequests;

    public function show(Supplier $supplier, EligibilityService $eligibility): JsonResponse
    {
        $this->authorize('viewEligibility', $supplier);

        $evaluation = $eligibility->evaluate($supplier);
        unset($evaluation['satisfying_record_ids']);

        return ApiResponse::success(data: $evaluation);
    }
}
