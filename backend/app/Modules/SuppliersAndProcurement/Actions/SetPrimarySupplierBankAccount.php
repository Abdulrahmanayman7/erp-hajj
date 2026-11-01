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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

final class SetPrimarySupplierBankAccount
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

        return BankingTransaction::run(function () use ($actor, $supplier, $account, $request, $tenant): SupplierBankAccount {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            // Lock family/supplier bank rows in deterministic id order (lock order §8).
            SupplierBankAccount::query()
                ->where('supplier_id', $supplier->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked = SupplierBankAccount::query()
                ->whereKey($account->id)
                ->where('supplier_id', $supplier->id)
                ->firstOrFail();

            if ($locked->status !== SupplierBankAccountStatus::Verified) {
                throw SupplierDomainException::bankPrimaryNotVerified();
            }

            try {
                SupplierBankAccount::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('is_primary', true)
                    ->whereKeyNot($locked->id)
                    ->update(['is_primary' => false]);

                $locked->forceFill(['is_primary' => true])->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::bankPrimaryNotVerified();
            }

            $fresh = $locked->fresh();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_PRIMARY_SET, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $fresh->id,
                'supplier_id' => $supplier->id,
                'account_key' => $fresh->account_key,
                'version_no' => $fresh->version_no,
                'iban_last4' => $fresh->iban_last4,
                'status' => $fresh->status?->value,
            ], $request);

            return $fresh;
        });
    }
}
