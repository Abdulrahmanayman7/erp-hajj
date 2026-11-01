<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountEditorRole;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use App\Modules\SuppliersAndProcurement\Support\SupplierBankEvidence;
use App\Modules\SuppliersAndProcurement\Support\SupplierBankIdentifiers;
use Illuminate\Http\Request;

final class SupersedeSupplierBankAccount
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
    public function execute(
        User $actor,
        Supplier $supplier,
        SupplierBankAccount $prior,
        array $data,
        ?Request $request = null,
    ): SupplierBankAccount {
        $tenant = $this->tenantContext->require();

        return BankingTransaction::run(function () use ($actor, $supplier, $prior, $data, $request, $tenant): SupplierBankAccount {
            $lockedSupplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            $lockedPrior = SupplierBankAccount::query()
                ->whereKey($prior->id)
                ->where('supplier_id', $lockedSupplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPrior->status !== SupplierBankAccountStatus::Verified) {
                throw SupplierDomainException::bankInvalidStatus();
            }

            if ((int) $lockedPrior->current_slot !== 0) {
                throw SupplierDomainException::bankInvalidStatus();
            }

            // E5: complete identifiers required — never copy ciphertext from prior.
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
            $evidenceId = array_key_exists('evidence_document_id', $data)
                ? $this->evidence->resolveOptional($lockedSupplier, $data['evidence_document_id'])
                : null;

            $label = array_key_exists('label', $data)
                ? (trim((string) $data['label']) ?: null)
                : $lockedPrior->label;

            $nextVersion = (int) $lockedPrior->version_no + 1;

            // Demote prior off family head; retain verified + primary (IIB-D11).
            $lockedPrior->forceFill([
                'current_slot' => $lockedPrior->id,
            ])->save();

            $replacement = new SupplierBankAccount([
                'supplier_id' => $lockedSupplier->id,
                'account_key' => $lockedPrior->account_key,
                'version_no' => $nextVersion,
                'label' => $label,
                'account_holder_name' => trim((string) $data['account_holder_name']),
                'bank_name' => trim((string) $data['bank_name']),
                'iban_last4' => $prepared['iban_last4'],
                'account_last4' => $prepared['account_last4'],
                'currency' => 'SAR',
                'status' => SupplierBankAccountStatus::Pending,
                'current_slot' => 0,
                'is_primary' => false,
                'supersedes_id' => $lockedPrior->id,
                'evidence_document_id' => $evidenceId,
                'created_by' => $actor->id,
                'last_edited_by' => $actor->id,
            ]);
            $replacement->save();
            $replacement->storeEncryptedIdentifiers(
                $prepared['iban'],
                $prepared['account'],
                $prepared['fingerprint'],
            );
            $replacement->recordEditor($actor->id, SupplierBankAccountEditorRole::Creator->value);

            if ($prepared['fingerprint'] !== null) {
                $this->claims->acquireOrRefresh($replacement->fresh());
            }

            // Different-IBAN: prior verified still sustains old claim — do not release.
            // Same-IBAN: acquireOrRefresh refreshed holder to replacement.

            $fresh = $replacement->fresh();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_SUPERSEDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $fresh->id,
                'supplier_id' => $lockedSupplier->id,
                'account_key' => $fresh->account_key,
                'version_no' => $fresh->version_no,
                'superseded_id' => $lockedPrior->id,
                'prior_remains_primary' => (bool) $lockedPrior->fresh()->is_primary,
                'iban_last4' => $fresh->iban_last4,
                'status' => $fresh->status?->value,
            ], $request);

            return $fresh;
        });
    }
}
