<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\ClearPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RejectSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RevealSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RevokeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SetPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SupersedeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePendingSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\VerifySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Requests\ClearPrimarySupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\RejectSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\RevealSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\RevokeSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\SetPrimarySupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\SupersedeSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdatePendingSupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Requests\VerifySupplierBankAccountRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierBankAccountResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierBankAccountController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AuthorizationSecurity $security,
    ) {}

    public function index(Supplier $supplier): JsonResponse
    {
        $this->authorize('viewBanking', $supplier);

        $accounts = SupplierBankAccount::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('account_key')
            ->orderByDesc('version_no')
            ->get();

        return ApiResponse::success(
            data: SupplierBankAccountResource::collection($accounts)->resolve(),
        );
    }

    public function show(Supplier $supplier, SupplierBankAccount $bankAccount): JsonResponse
    {
        $this->authorize('viewBanking', $supplier);

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($bankAccount))->resolve(),
        );
    }

    public function store(
        CreateSupplierBankAccountRequest $request,
        Supplier $supplier,
        CreateSupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute($request->user(), $supplier, $request->bankingPayload(), $request);

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم إنشاء الحساب البنكي',
            status: 201,
        );
    }

    public function update(
        UpdatePendingSupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        UpdatePendingSupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute(
            $request->user(),
            $supplier,
            $bankAccount,
            $request->bankingPayload(),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم تحديث الحساب البنكي',
        );
    }

    public function verify(
        VerifySupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        VerifySupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('verifyBanking', $supplier);

        $account = $action->execute($request->user(), $supplier, $bankAccount, $request);

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم التحقق من الحساب البنكي',
        );
    }

    public function reject(
        RejectSupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        RejectSupplierBankAccount $action,
    ): JsonResponse {
        // Design §5.1: reject uses verify_banking (SoD still enforced in Action).
        $this->authorize('verifyBanking', $supplier);

        $account = $action->execute(
            $request->user(),
            $supplier,
            $bankAccount,
            (string) $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم رفض الحساب البنكي',
        );
    }

    public function revoke(
        RevokeSupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        RevokeSupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute(
            $request->user(),
            $supplier,
            $bankAccount,
            (string) $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم إبطال الحساب البنكي',
        );
    }

    public function supersede(
        SupersedeSupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        SupersedeSupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute(
            $request->user(),
            $supplier,
            $bankAccount,
            $request->bankingPayload(),
            $request,
        );

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم إنشاء إصدار بديل للحساب البنكي',
            status: 201,
        );
    }

    public function setPrimary(
        SetPrimarySupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        SetPrimarySupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute($request->user(), $supplier, $bankAccount, $request);

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم تعيين الحساب البنكي الأساسي',
        );
    }

    public function clearPrimary(
        ClearPrimarySupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        ClearPrimarySupplierBankAccount $action,
    ): JsonResponse {
        $this->authorize('manageBanking', $supplier);

        $account = $action->execute($request->user(), $supplier, $bankAccount, $request);

        return ApiResponse::success(
            data: (new SupplierBankAccountResource($account))->resolve(),
            message: 'تم إلغاء تعيين الحساب البنكي الأساسي',
        );
    }

    public function reveal(
        RevealSupplierBankAccountRequest $request,
        Supplier $supplier,
        SupplierBankAccount $bankAccount,
        RevealSupplierBankAccount $action,
    ): JsonResponse {
        try {
            $this->authorize('revealBanking', $supplier);
        } catch (AuthorizationException $e) {
            // Same-tenant resource already resolved — audit denial without decrypting.
            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEAL_DENIED, [
                'tenant_id' => $supplier->tenant_id,
                'actor_id' => $request->user()?->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $bankAccount->id,
                'supplier_id' => $supplier->id,
                'denial_code' => 'permission_denied',
                'outcome' => 'denied',
            ], $request);

            throw $e;
        }

        $payload = $action->execute(
            $request->user(),
            $supplier,
            $bankAccount,
            (string) $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(
            data: $payload,
            message: 'تم إظهار المعرفات البنكية',
        )->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }
}
