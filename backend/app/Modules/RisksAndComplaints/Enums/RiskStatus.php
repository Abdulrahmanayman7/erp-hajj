<?php

namespace App\Modules\RisksAndComplaints\Enums;

enum RiskStatus: string
{
    case Identified = 'identified';
    case UnderAssessment = 'under_assessment';
    case Approved = 'approved';
    case Treating = 'treating';
    case Closed = 'closed';

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    public function allowsSoftDelete(): bool
    {
        return $this === self::Identified;
    }
}
