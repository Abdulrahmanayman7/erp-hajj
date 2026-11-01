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

final class RejectSupplierBankAccount
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

        try {
            return BankingTransaction::run(function () use ($actor, $supplier, $account, $reason, $request, $tenant): SupplierBankAccount {
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

                $fingerprint = $locked->ibanFingerprintValue();

                $locked->forceFill([
                    'status' => SupplierBankAccountStatus::Rejected,
                    'is_primary' => false,
                    'rejected_by' => $actor->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
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

                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REJECTED, [
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
        } catch (SupplierDomainException $e) {
            if ($e->errorCode() === 'SUPPLIER_BANK_SOD_VIOLATION') {
                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_bank_account',
                    'entity_id' => $account->id,
                    'supplier_id' => $supplier->id,
                    'attempted_action' => 'reject_bank_account',
                ], $request);
            }

            throw $e;
        }
    }
}
