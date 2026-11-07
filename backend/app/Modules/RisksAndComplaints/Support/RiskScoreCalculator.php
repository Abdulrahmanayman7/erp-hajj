<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;

/**
 * Q3 / D02 — score = likelihood × impact; severity from approved bands.
 */
final class RiskScoreCalculator
{
    /**
     * @return array{score: int, severity: RiskSeverity}
     */
    public function calculate(int $likelihood, int $impact): array
    {
        if ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5) {
            throw RisksAndComplaintsDomainException::riskMatrixOutOfRange();
        }

        $score = $likelihood * $impact;

        return [
            'score' => $score,
            'severity' => RiskSeverity::fromScore($score),
        ];
    }
}
