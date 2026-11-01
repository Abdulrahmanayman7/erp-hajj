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

final class UpdatePendingSupplierBankAccount
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
        SupplierBankAccount $account,
        array $data,
        ?Request $request = null,
    ): SupplierBankAccount {
        $tenant = $this->tenantContext->require();

        return BankingTransaction::run(function () use ($actor, $supplier, $account, $data, $request, $tenant): SupplierBankAccount {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            $locked = SupplierBankAccount::query()
                ->whereKey($account->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SupplierBankAccountStatus::Pending || (int) $locked->current_slot !== 0) {
                throw SupplierDomainException::bankInvalidStatus();
            }

            if (array_key_exists('account_holder_name', $data) || array_key_exists('bank_name', $data)) {
                $this->identifiers->assertHolderAndBank(
                    array_key_exists('account_holder_name', $data)
                        ? (string) $data['account_holder_name']
                        : (string) $locked->account_holder_name,
                    array_key_exists('bank_name', $data)
                        ? (string) $data['bank_name']
                        : (string) $locked->bank_name,
                );
            }

            if (array_key_exists('currency', $data)) {
                $this->identifiers->assertCurrencySar((string) $data['currency']);
            }

            $oldFingerprint = $locked->ibanFingerprintValue();
            $ibanTouched = array_key_exists('iban', $data);
            $accountTouched = array_key_exists('account_number', $data);
            $identifierTouched = $ibanTouched || $accountTouched;

            if ($ibanTouched) {
                $raw = trim((string) ($data['iban'] ?? ''));
                if ($raw === '') {
                    $locked->forceFill([
                        'iban_last4' => null,
                        'iban_ciphertext' => null,
                        'iban_fingerprint' => null,
                    ])->save();
                } else {
                    $prepared = $this->identifiers->prepare((int) $tenant->id, $raw, null);
                    $locked->forceFill([
                        'iban_last4' => $prepared['iban_last4'],
                        'iban_ciphertext' => $prepared['iban'],
                        'iban_fingerprint' => $prepared['fingerprint'],
                    ])->save();
                }
            }

            if ($accountTouched) {
                $raw = trim((string) ($data['account_number'] ?? ''));
                if ($raw === '') {
                    $locked->forceFill([
                        'account_last4' => null,
                        'account_ciphertext' => null,
                    ])->save();
                } else {
                    $prepared = $this->identifiers->prepare((int) $tenant->id, null, $raw);
                    $locked->forceFill(['account_last4' => $prepared['account_last4']])->save();
                    $locked->forceFill([
                        'account_ciphertext' => $prepared['account'],
                    ])->save();
                }
            }

            $locked = $locked->fresh();
            if ($locked->ibanFingerprintValue() === null && $locked->account_last4 === null) {
                throw SupplierDomainException::bankIdentifierRequired();
            }

            if ($identifierTouched) {
                $newFingerprint = $locked->ibanFingerprintValue();
                if ($newFingerprint !== null) {
                    $this->claims->acquireOrRefresh($locked);
                }
                if ($oldFingerprint !== null && $oldFingerprint !== $newFingerprint) {
                    $this->claims->releaseIfSafe(
                        (int) $tenant->id,
                        (int) $locked->supplier_id,
                        (string) $locked->account_key,
                        $oldFingerprint,
                    );
                }
            }

            $meta = ['last_edited_by' => $actor->id];
            if (array_key_exists('label', $data)) {
                $label = trim((string) $data['label']);
                $meta['label'] = $label === '' ? null : $label;
            }
            if (array_key_exists('account_holder_name', $data)) {
                $meta['account_holder_name'] = trim((string) $data['account_holder_name']);
            }
            if (array_key_exists('bank_name', $data)) {
                $meta['bank_name'] = trim((string) $data['bank_name']);
            }
            if (array_key_exists('evidence_document_id', $data)) {
                $meta['evidence_document_id'] = $this->evidence->resolveOptional(
                    $supplier,
                    $data['evidence_document_id'],
                );
            }

            $locked->forceFill($meta)->save();
            $locked->recordEditor($actor->id, SupplierBankAccountEditorRole::Editor->value);

            $fresh = $locked->fresh();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $fresh->id,
                'supplier_id' => $supplier->id,
                'account_key' => $fresh->account_key,
                'version_no' => $fresh->version_no,
                'iban_last4' => $fresh->iban_last4,
                'account_last4' => $fresh->account_last4,
                'status' => $fresh->status?->value,
                'identifiers_changed' => $identifierTouched,
            ], $request);

            return $fresh;
        });
    }
}
