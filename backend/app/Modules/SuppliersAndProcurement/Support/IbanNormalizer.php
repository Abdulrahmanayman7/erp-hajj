<?php

namespace App\Modules\SuppliersAndProcurement\Support;

/**
 * Deterministic IBAN normalization for fingerprinting (design §4.2).
 */
final class IbanNormalizer
{
    public function normalize(string $iban): string
    {
        $trimmed = trim($iban);
        $upper = mb_strtoupper($trimmed, 'UTF-8');

        return preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
    }
}
