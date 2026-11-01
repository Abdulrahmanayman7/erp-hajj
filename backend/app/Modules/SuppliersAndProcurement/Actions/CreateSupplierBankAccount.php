<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountEditorRole;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use App\Modules\SuppliersAndProcurement\Support\SupplierBankEvidence;
use App\Modules\SuppliersAndProcurement\Support\SupplierBankIdentifiers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CreateSupplierBankAccount
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierBankIdentifiers $identifiers,
        private readonly SupplierBankEvidence $evidence,
        private readonly BankIbanClaimService $claims,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Supplier $supplier, array $data, ?Request $request = null): SupplierBankAccount
    {
        $tenant = $this->tenantContext->require();

        return BankingTransaction::run(function () use ($actor, $supplier, $data, $request, $tenant): SupplierBankAccount {
            $lockedSupplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            $this->identifiers->assertHolderAndBank(
                isset($data['account_holder_name']) ? (string) $data['account_holder_name'] : null,
                isset($data['bank_name']) ? (string) $data['bank_name'] : null,
            );
            $this->identifiers->assertCurrencySar(isset($data['currency']) ? (string) $data['currency'] : 'SAR');

            $prepared = $this->identifiers->prepare(
                (int) $tenant->id,
                isset($data['iban']) ? (string) $data['iban'] : null,
                isset($data['account_number']) ? (string) $data['account_number'] : null,
            );
            $evidenceId = $this->evidence->resolveOptional(
                $lockedSupplier,
                $data['evidence_document_id'] ?? null,
            );

            $account = new SupplierBankAccount([
                'supplier_id' => $lockedSupplier->id,
                'account_key' => (string) Str::uuid(),
                'version_no' => 1,
                'label' => isset($data['label']) ? trim((string) $data['label']) ?: null : null,
                'account_holder_name' => trim((string) $data['account_holder_name']),
                'bank_name' => trim((string) $data['bank_name']),
                'iban_last4' => $prepared['iban_last4'],
                'account_last4' => $prepared['account_last4'],
                'currency' => 'SAR',
                'status' => SupplierBankAccountStatus::Pending,
                'current_slot' => 0,
                'is_primary' => false,
                'evidence_document_id' => $evidenceId,
                'created_by' => $actor->id,
                'last_edited_by' => $actor->id,
            ]);
            $account->save();

            $account->storeEncryptedIdentifiers(
                $prepared['iban'],
                $prepared['account'],
                $prepared['fingerprint'],
            );
            $account->recordEditor($actor->id, SupplierBankAccountEditorRole::Creator->value);

            if ($prepared['fingerprint'] !== null) {
                $this->claims->acquireOrRefresh($account->fresh());
            }

            $fresh = $account->fresh();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $fresh->id,
                'supplier_id' => $lockedSupplier->id,
                'account_key' => $fresh->account_key,
                'version_no' => $fresh->version_no,
                'iban_last4' => $fresh->iban_last4,
                'account_last4' => $fresh->account_last4,
                'status' => $fresh->status?->value,
            ], $request);

            return $fresh;
        });
    }
}
