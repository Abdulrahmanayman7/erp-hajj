<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\AcceptSupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Actions\RejectSupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Requests\RejectSupplierEvaluationAppealRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierEvaluationAppealResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierEvaluationAppealController
{
    use AuthorizesRequests;

    public function accept(
        Request $request,
        SupplierEvaluationAppeal $appeal,
        AcceptSupplierEvaluationAppeal $action,
    ): JsonResponse {
        $this->authorize('accept', $appeal);

        $appeal = $action->execute($request->user(), $appeal, $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationAppealResource($appeal))->resolve(),
            message: 'تم قبول الاعتراض وإنشاء مسودة تصحيح',
        );
    }

    public function reject(
        RejectSupplierEvaluationAppealRequest $request,
        SupplierEvaluationAppeal $appeal,
        RejectSupplierEvaluationAppeal $action,
    ): JsonResponse {
        $this->authorize('reject', $appeal);

        $appeal = $action->execute($request->user(), $appeal, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierEvaluationAppealResource($appeal))->resolve(),
            message: 'تم رفض الاعتراض',
        );
    }
}
