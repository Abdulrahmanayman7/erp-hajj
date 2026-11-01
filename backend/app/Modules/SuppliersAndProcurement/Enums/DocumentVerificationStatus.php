<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum DocumentVerificationStatus: string
{
    case PendingVerification = 'pending_verification';
    case Valid = 'valid';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    /**
     * States from which a record may still be superseded by a replacement.
     */
    public function isSupersedable(): bool
    {
        return $this !== self::Superseded;
    }
}
