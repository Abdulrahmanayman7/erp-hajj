<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;

/**
 * Normalize/validate banking identifiers without logging plaintext values.
 *
 * @phpstan-type PreparedIdentifiers array{
 *     iban: ?string,
 *     account: ?string,
 *     iban_last4: ?string,
 *     account_last4: ?string,
 *     fingerprint: ?string
 * }
 */
final class SupplierBankIdentifiers
{
    public function __construct(
        private readonly IbanNormalizer $normalizer,
        private readonly IbanFingerprint $fingerprint,
    ) {}

    /**
     * @return PreparedIdentifiers
     */
    public function prepare(int $tenantId, ?string $iban, ?string $accountNumber): array
    {
        $ibanPlain = $this->nullableTrim($iban);
        $accountPlain = $this->nullableTrim($accountNumber);

        if ($ibanPlain === null && $accountPlain === null) {
            throw SupplierDomainException::bankIdentifierRequired();
        }

        $normalizedIban = null;
        $fingerprint = null;
        $ibanLast4 = null;

        if ($ibanPlain !== null) {
            $normalizedIban = $this->normalizer->normalize($ibanPlain);
            if ($normalizedIban === '') {
                throw SupplierDomainException::bankIdentifierRequired();
            }
            $this->assertStructuralIban($normalizedIban);
            $fingerprint = $this->fingerprint->fingerprint($tenantId, $normalizedIban);
            $ibanLast4 = substr($normalizedIban, -4);
        }

        $accountLast4 = null;
        if ($accountPlain !== null) {
            $digits = preg_replace('/\s+/', '', $accountPlain) ?? '';
            if ($digits === '') {
                throw SupplierDomainException::bankIdentifierRequired();
            }
            $accountPlain = $digits;
            $accountLast4 = substr($accountPlain, -4);
        }

        return [
            'iban' => $normalizedIban,
            'account' => $accountPlain,
            'iban_last4' => $ibanLast4,
            'account_last4' => $accountLast4,
            'fingerprint' => $fingerprint,
        ];
    }

    public function assertCurrencySar(?string $currency): void
    {
        $value = $currency === null || $currency === '' ? 'SAR' : strtoupper(trim($currency));
        if ($value !== 'SAR') {
            throw SupplierDomainException::bankCurrencyUnsupported();
        }
    }

    public function assertHolderAndBank(?string $holderName, ?string $bankName): void
    {
        if ($this->nullableTrim($holderName) === null) {
            throw SupplierDomainException::bankHolderNameRequired();
        }
        if ($this->nullableTrim($bankName) === null) {
            throw SupplierDomainException::bankNameRequired();
        }
    }

    private function assertStructuralIban(string $normalized): void
    {
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $normalized)) {
            throw SupplierDomainException::bankIdentifierRequired();
        }

        if (str_starts_with($normalized, 'SA') && strlen($normalized) !== 24) {
            throw SupplierDomainException::bankIdentifierRequired();
        }

        // Recommended MOD-97 checksum (design §4.2) — reject invalid without echoing value.
        $rearranged = substr($normalized, 4).substr($normalized, 0, 4);
        $numeric = '';
        $len = strlen($rearranged);
        for ($i = 0; $i < $len; $i++) {
            $ch = $rearranged[$i];
            if ($ch >= '0' && $ch <= '9') {
                $numeric .= $ch;
            } else {
                $numeric .= (string) (ord($ch) - 55);
            }
        }

        if (bcmod($numeric, '97') !== '1') {
            throw SupplierDomainException::bankIdentifierRequired();
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
