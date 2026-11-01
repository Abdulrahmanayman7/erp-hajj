<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Actions\ListSupplierDocumentRecords;
use App\Modules\SuppliersAndProcurement\Actions\RejectSupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Actions\SupersedeSupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Actions\VerifySupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierDocumentRecordRequest;
use App\Modules\SuppliersAndProcurement\Requests\RejectSupplierDocumentRecordRequest;
use App\Modules\SuppliersAndProcurement\Requests\SupersedeSupplierDocumentRecordRequest;
use App\Modules\SuppliersAndProcurement\Requests\VerifySupplierDocumentRecordRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierDocumentRecordResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierDocumentRecordController
{
    use AuthorizesRequests;

    public function index(Supplier $supplier, ListSupplierDocumentRecords $action): JsonResponse
    {
        $this->authorize('viewDocumentRecords', $supplier);

        return ApiResponse::success(
            data: SupplierDocumentRecordResource::collection($action->execute($supplier))->resolve(),
        );
    }

    public function store(
        CreateSupplierDocumentRecordRequest $request,
        Supplier $supplier,
        CreateSupplierDocumentRecord $action,
    ): JsonResponse {
        $this->authorize('manageDocuments', $supplier);

        $record = $action->execute($request->user(), $supplier, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierDocumentRecordResource($record))->resolve(),
            message: 'تم إنشاء سجل مستند التأهيل',
            status: 201,
        );
    }

    public function verify(
        VerifySupplierDocumentRecordRequest $request,
        Supplier $supplier,
        SupplierDocumentRecord $supplierDocumentRecord,
        VerifySupplierDocumentRecord $action,
    ): JsonResponse {
        $this->authorize('manageDocuments', $supplier);

        $record = $action->execute(
            $request->user(),
            $supplier,
            $supplierDocumentRecord,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierDocumentRecordResource($record))->resolve(),
            message: 'تم التحقق من المستند',
        );
    }

    public function reject(
        RejectSupplierDocumentRecordRequest $request,
        Supplier $supplier,
        SupplierDocumentRecord $supplierDocumentRecord,
        RejectSupplierDocumentRecord $action,
    ): JsonResponse {
        $this->authorize('manageDocuments', $supplier);

        $record = $action->execute(
            $request->user(),
            $supplier,
            $supplierDocumentRecord,
            (string) $request->validated('rejection_reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierDocumentRecordResource($record))->resolve(),
            message: 'تم رفض المستند',
        );
    }

    public function supersede(
        SupersedeSupplierDocumentRecordRequest $request,
        Supplier $supplier,
        SupplierDocumentRecord $supplierDocumentRecord,
        SupersedeSupplierDocumentRecord $action,
    ): JsonResponse {
        $this->authorize('manageDocuments', $supplier);

        $record = $action->execute(
            $request->user(),
            $supplier,
            $supplierDocumentRecord,
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierDocumentRecordResource($record))->resolve(),
            message: 'تم استبدال سجل المستند',
            status: 201,
        );
    }
}
