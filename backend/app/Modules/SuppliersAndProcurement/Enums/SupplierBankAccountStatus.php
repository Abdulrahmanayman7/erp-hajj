<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum SupplierBankAccountStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    /**
     * Statuses that sustain a live IBAN claim for a family fingerprint (design §2.4).
     */
    public function sustainsIbanClaim(): bool
    {
        return $this === self::Pending || $this === self::Verified;
    }
}
