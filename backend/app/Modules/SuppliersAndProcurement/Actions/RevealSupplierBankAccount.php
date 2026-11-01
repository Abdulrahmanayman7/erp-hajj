<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use Illuminate\Http\Request;

/**
 * IIB-D12 / E1 audited reveal. Call only after Policy revealBanking succeeds.
 * Does not authorize — controller must authorize before invoking to avoid decrypt-on-deny.
 */
final class RevealSupplierBankAccount
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     supplier_id: int,
     *     account_key: string,
     *     version_no: int,
     *     status: string|null,
     *     iban: ?string,
     *     account_number: ?string,
     *     iban_last4: ?string,
     *     account_last4: ?string
     * }
     */
    public function execute(
        User $actor,
        Supplier $supplier,
        SupplierBankAccount $account,
        string $reason,
        Request $request,
    ): array {
        $tenant = $this->tenantContext->require();
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEAL_DENIED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_bank_account',
                'entity_id' => $account->id,
                'supplier_id' => $supplier->id,
                'denial_code' => 'reason_too_short',
                'reason_length' => mb_strlen($reason),
                'outcome' => 'denied',
            ], $request);

            throw SupplierDomainException::bankRevealReasonRequired();
        }

        if ((int) $account->supplier_id !== (int) $supplier->id) {
            throw SupplierDomainException::bankNotFound();
        }

        // Decrypt only after authorization (controller) and reason validation.
        $iban = $account->iban_ciphertext;
        $local = $account->account_ciphertext;

        $this->security->record(AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEALED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'entity_type' => 'supplier_bank_account',
            'entity_id' => $account->id,
            'supplier_id' => $supplier->id,
            'account_key' => $account->account_key,
            'version_no' => $account->version_no,
            'status' => $account->status?->value,
            'iban_last4' => $account->iban_last4,
            'account_last4' => $account->account_last4,
            // Persist length only — never raw reason text (Owner WP-03 R1).
            'reason_length' => mb_strlen($reason),
            'outcome' => 'success',
        ], $request);

        return [
            'id' => (int) $account->id,
            'supplier_id' => (int) $supplier->id,
            'account_key' => (string) $account->account_key,
            'version_no' => (int) $account->version_no,
            'status' => $account->status?->value,
            'iban' => is_string($iban) && $iban !== '' ? $iban : null,
            'account_number' => is_string($local) && $local !== '' ? $local : null,
            'iban_last4' => $account->iban_last4,
            'account_last4' => $account->account_last4,
        ];
    }
}
