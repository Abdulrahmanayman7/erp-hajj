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
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use Illuminate\Http\Request;

final class RevokeSupplierBankAccount
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BankIbanClaimService $claims,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        Supplier $supplier,
        SupplierBankAccount $account,
        string $reason,
        ?Request $request = null,
    ): SupplierBankAccount {
        $tenant = $this->tenantContext->require();
        $reason = trim($reason);
        if ($reason === '') {
            throw SupplierDomainException::bankInvalidStatus();
        }

        return BankingTransaction::run(function () use ($actor, $supplier, $account, $reason, $request, $tenant): SupplierBankAccount {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            $locked = SupplierBankAccount::query()
                ->whereKey($account->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SupplierBankAccountStatus::Verified) {
                throw SupplierDomainException::bankInvalidStatus();
            }

            $fingerprint = $locked->ibanFingerprintValue();
            $wasPrimary = (bool) $locked->is_primary;

            $locked->forceFill([
                'status' => SupplierBankAccountStatus::Revoked,
                'is_primary' => false,
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ])->save();

            if ($fingerprint !== null) {
                $this->claims->releaseIfSafe(
                    (int) $tenant->id,
                    (int) $locked->supplier_id,
                    (string) $locked->account_key,
                    $fingerprint,
                );
            }

            $fresh = $locked->fresh();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVOKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $fresh->id,
                'supplier_id' => $supplier->id,
                'account_key' => $fresh->account_key,
                'version_no' => $fresh->version_no,
                'iban_last4' => $fresh->iban_last4,
                'was_primary' => $wasPrimary,
                'status' => $fresh->status?->value,
            ], $request);

            return $fresh;
        });
    }
}
