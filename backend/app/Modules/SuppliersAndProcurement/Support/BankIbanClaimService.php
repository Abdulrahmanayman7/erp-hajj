<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * WP-01 low-level IBAN claim primitives (C1 / E3 / M1–M2 foundations).
 *
 * Lock order (design): supplier → claim rows (fingerprint ascending) → family bank rows → editors.
 *
 * Caller transaction contract (R1):
 * - Prefer invoking acquireOrRefresh / releaseIfSafe as the outermost DB transaction for claim
 *   work so a single bounded deadlock retry can safely re-run the full callback.
 * - When already inside a caller transaction (WP-02 Actions), this service does NOT retry on
 *   deadlock: MySQL aborts the entire underlying transaction, and nested savepoint retry would
 *   be unsafe. The caller must retry the whole outer transaction once.
 * - Unique (tenant_id, iban_fingerprint) conflicts are recovered in-place (MySQL rolls back only
 *   the failed statement) and mapped to same-family holder refresh or SUPPLIER_BANK_IBAN_DUPLICATE.
 *
 * Does not implement create/PATCH/verify/reject/revoke/supersede workflows (WP-02).
 */
final class BankIbanClaimService
{
    /** Outermost-only: initial attempt + one deadlock retry. */
    private const DEADLOCK_ATTEMPTS = 2;

    /**
     * Acquire a new claim or refresh the holder for the same family.
     * Rejects cross-family ownership. Never reassigns account_key/supplier_id on an existing claim.
     */
    public function acquireOrRefresh(SupplierBankAccount $holder): SupplierBankIbanClaim
    {
        $fingerprint = $this->holderFingerprint($holder);
        $this->assertHolderReady($holder);

        return $this->runClaimTransaction(function () use ($holder, $fingerprint): SupplierBankIbanClaim {
            // Lock order step 1: supplier row.
            Supplier::query()->whereKey((int) $holder->supplier_id)->lockForUpdate()->firstOrFail();

            // Lock order step 2: claim row for this fingerprint (deterministic single-key order).
            $claim = SupplierBankIbanClaim::query()
                ->where('iban_fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($claim !== null) {
                return $this->refreshExistingClaim($claim, $holder, $fingerprint);
            }

            try {
                /** @var SupplierBankIbanClaim $created */
                $created = SupplierBankIbanClaim::query()->create([
                    'iban_fingerprint' => $fingerprint,
                    'supplier_id' => (int) $holder->supplier_id,
                    'account_key' => (string) $holder->account_key,
                    'holder_bank_account_id' => (int) $holder->id,
                ]);

                return $created;
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isClaimFingerprintUniqueViolation($exception)) {
                    throw $exception;
                }

                // Competing INSERT lost: re-read winner under lock and apply ownership rules.
                $winning = SupplierBankIbanClaim::query()
                    ->where('iban_fingerprint', $fingerprint)
                    ->lockForUpdate()
                    ->first();

                if ($winning === null) {
                    throw new LogicException('IBAN claim unique conflict occurred but no winning claim row is visible.');
                }

                return $this->refreshExistingClaim($winning, $holder, $fingerprint);
            }
        });
    }

    /**
     * Hard-delete the claim when no pending/verified version in the family still requires the fingerprint.
     *
     * @return bool true when a claim row was deleted
     */
    public function releaseIfSafe(int $tenantId, int $supplierId, string $accountKey, string $fingerprint): bool
    {
        return $this->runClaimTransaction(function () use ($tenantId, $supplierId, $accountKey, $fingerprint): bool {
            // Lock order: supplier → claim → family bank rows.
            Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

            $claim = SupplierBankIbanClaim::query()
                ->where('iban_fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($claim === null) {
                return false;
            }

            if ((int) $claim->tenant_id !== $tenantId
                || (int) $claim->supplier_id !== $supplierId
                || (string) $claim->account_key !== $accountKey) {
                throw new LogicException('Claim ownership does not match the requested release family.');
            }

            $stillRequired = SupplierBankAccount::query()
                ->where('supplier_id', $supplierId)
                ->where('account_key', $accountKey)
                ->where('iban_fingerprint', $fingerprint)
                ->whereIn('status', [
                    SupplierBankAccountStatus::Pending->value,
                    SupplierBankAccountStatus::Verified->value,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->exists();

            if ($stillRequired) {
                return false;
            }

            // Hard removal (M2) — bypass append-only guards; claims are not soft-deleted.
            SupplierBankIbanClaim::query()->whereKey($claim->id)->delete();

            return true;
        });
    }

    public function assertHolderReady(SupplierBankAccount $holder): void
    {
        if ($holder->id === null) {
            throw new LogicException('Holder bank account must be persisted before claim acquire.');
        }

        if ((string) $holder->account_key === '') {
            throw new LogicException('Holder bank account must have an account_key.');
        }

        if ((int) $holder->tenant_id <= 0 || (int) $holder->supplier_id <= 0) {
            throw new LogicException('Holder must have tenant_id and supplier_id.');
        }
    }

    /**
     * Composite ownership that MySQL cannot fully express with FKs alone.
     */
    public function assertClaimMatchesHolder(
        SupplierBankIbanClaim $claim,
        SupplierBankAccount $holder,
        string $fingerprint,
    ): void {
        $claimFp = (string) ($claim->getAttributes()['iban_fingerprint'] ?? '');

        if ((int) $claim->tenant_id !== (int) $holder->tenant_id
            || (int) $claim->supplier_id !== (int) $holder->supplier_id
            || (string) $claim->account_key !== (string) $holder->account_key
            || $claimFp !== $fingerprint
            || (int) $claim->holder_bank_account_id !== (int) $holder->id) {
            throw new LogicException('IBAN claim ownership is inconsistent with the holder bank-account version.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function runClaimTransaction(callable $callback): mixed
    {
        // Deadlock retry is safe only as the outermost transaction. Nested callers own retry.
        $attempts = DB::transactionLevel() === 0 ? self::DEADLOCK_ATTEMPTS : 1;

        return DB::transaction($callback, $attempts);
    }

    private function refreshExistingClaim(
        SupplierBankIbanClaim $claim,
        SupplierBankAccount $holder,
        string $fingerprint,
    ): SupplierBankIbanClaim {
        if ((string) $claim->account_key !== (string) $holder->account_key
            || (int) $claim->supplier_id !== (int) $holder->supplier_id) {
            throw SupplierDomainException::bankIbanDuplicate();
        }

        // Same family: refresh holder only — never change account_key / supplier_id.
        $claim->forceFill([
            'holder_bank_account_id' => (int) $holder->id,
        ])->save();

        $fresh = $claim->fresh();
        if ($fresh === null) {
            throw new LogicException('IBAN claim disappeared after holder refresh.');
        }

        $this->assertClaimMatchesHolder($fresh, $holder, $fingerprint);

        return $fresh;
    }

    private function isClaimFingerprintUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        $haystack = strtolower($exception->getMessage());

        return str_contains($haystack, 'uq_bank_iban_claim_tenant_fp')
            || str_contains($haystack, 'supplier_bank_iban_claims');
    }

    private function holderFingerprint(SupplierBankAccount $holder): string
    {
        $fingerprint = $holder->ibanFingerprintValue();
        if ($fingerprint === null) {
            $raw = $holder->getRawOriginal('iban_fingerprint');
            $fingerprint = is_string($raw) && $raw !== '' ? $raw : null;
        }

        if ($fingerprint === null) {
            throw new LogicException('Cannot acquire an IBAN claim without iban_fingerprint on the holder version.');
        }

        return $fingerprint;
    }
}
