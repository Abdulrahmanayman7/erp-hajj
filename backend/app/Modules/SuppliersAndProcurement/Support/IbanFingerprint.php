<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use RuntimeException;

/**
 * Tenant-bound keyed HMAC fingerprint. Pepper must be configured and ≠ APP_KEY.
 */
final class IbanFingerprint
{
    public function __construct(
        private readonly IbanNormalizer $normalizer,
    ) {}

    public function fingerprint(int $tenantId, string $iban): string
    {
        $normalized = $this->normalizer->normalize($iban);
        if ($normalized === '') {
            throw new RuntimeException('Cannot fingerprint an empty IBAN after normalization.');
        }

        $pepper = $this->requirePepper();
        $message = $tenantId.':'.$normalized;

        return hash_hmac('sha256', $message, $pepper);
    }

    public function requirePepper(): string
    {
        $pepper = config('banking.iban_pepper');
        if (! is_string($pepper) || trim($pepper) === '') {
            throw new RuntimeException(
                'BANKING_IBAN_PEPPER is not configured. Banking fingerprinting fails closed.',
            );
        }

        $appKey = (string) config('app.key');
        if ($appKey !== '' && hash_equals($pepper, $appKey)) {
            throw new RuntimeException(
                'BANKING_IBAN_PEPPER must be distinct from APP_KEY.',
            );
        }

        // Also reject common Laravel "base64:..." APP_KEY equality after decode is not needed —
        // config('app.key') is the raw configured string; pepper must simply not equal it.
        return $pepper;
    }
}
