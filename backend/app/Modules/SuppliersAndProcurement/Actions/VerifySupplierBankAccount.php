<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use Illuminate\Http\Request;

final class VerifySupplierBankAccount
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        Supplier $supplier,
        SupplierBankAccount $account,
        ?Request $request = null,
    ): SupplierBankAccount {
        $tenant = $this->tenantContext->require();

        try {
            return BankingTransaction::run(function () use ($actor, $supplier, $account, $request, $tenant): SupplierBankAccount {
                Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

                $locked = SupplierBankAccount::query()
                    ->whereKey($account->id)
                    ->where('supplier_id', $supplier->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== SupplierBankAccountStatus::Pending) {
                    throw SupplierDomainException::bankInvalidStatus();
                }

                if ($locked->hasContributor((int) $actor->id)) {
                    throw SupplierDomainException::bankSodViolation();
                }

                // IIB-D11: verification never transfers primary.
                $locked->forceFill([
                    'status' => SupplierBankAccountStatus::Verified,
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ])->save();

                $fresh = $locked->fresh();

                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_VERIFIED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_bank_account',
                    'entity_id' => $fresh->id,
                    'supplier_id' => $supplier->id,
                    'account_key' => $fresh->account_key,
                    'version_no' => $fresh->version_no,
                    'iban_last4' => $fresh->iban_last4,
                    'is_primary' => (bool) $fresh->is_primary,
                    'status' => $fresh->status?->value,
                ], $request);

                return $fresh;
            });
        } catch (SupplierDomainException $e) {
            if ($e->errorCode() === 'SUPPLIER_BANK_SOD_VIOLATION') {
                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_bank_account',
                    'entity_id' => $account->id,
                    'supplier_id' => $supplier->id,
                    'attempted_action' => 'verify_bank_account',
                ], $request);
            }

            throw $e;
        }
    }
}
