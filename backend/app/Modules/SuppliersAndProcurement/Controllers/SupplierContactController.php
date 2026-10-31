<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierContact;
use App\Modules\SuppliersAndProcurement\Actions\DeleteSupplierContact;
use App\Modules\SuppliersAndProcurement\Actions\UpdateSupplierContact;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierContact;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierContactRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateSupplierContactRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierContactResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierContactController
{
    use AuthorizesRequests;

    public function index(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $contacts = $supplier->contacts()->orderByDesc('is_primary_contact')->orderBy('id')->get();

        return ApiResponse::success(
            data: SupplierContactResource::collection($contacts)->resolve(),
        );
    }

    public function store(
        CreateSupplierContactRequest $request,
        Supplier $supplier,
        CreateSupplierContact $action,
    ): JsonResponse {
        $this->authorize('manageContacts', $supplier);

        $contact = $action->execute($request->user(), $supplier, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierContactResource($contact))->resolve(),
            message: 'تم إضافة جهة الاتصال',
            status: 201,
        );
    }

    public function update(
        UpdateSupplierContactRequest $request,
        Supplier $supplier,
        SupplierContact $supplierContact,
        UpdateSupplierContact $action,
    ): JsonResponse {
        $this->authorize('manageContacts', $supplier);

        $contact = $action->execute(
            $request->user(),
            $supplier,
            $supplierContact,
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierContactResource($contact))->resolve(),
            message: 'تم تحديث جهة الاتصال',
        );
    }

    public function destroy(
        Request $request,
        Supplier $supplier,
        SupplierContact $supplierContact,
        DeleteSupplierContact $action,
    ): JsonResponse {
        $this->authorize('manageContacts', $supplier);

        $action->execute($request->user(), $supplier, $supplierContact, $request);

        return ApiResponse::success(data: null, message: 'تم حذف جهة الاتصال');
    }
}
