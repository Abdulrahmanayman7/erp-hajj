<?php

namespace App\Modules\RisksAndComplaints\Enums;

use InvalidArgumentException;

/**
 * Severity bands from score = likelihood × impact (S07-D02).
 */
enum RiskSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(int $score): self
    {
        if ($score < 1 || $score > 25) {
            throw new InvalidArgumentException('Risk score must be between 1 and 25.');
        }

        return match (true) {
            $score <= 4 => self::Low,
            $score <= 9 => self::Medium,
            $score <= 15 => self::High,
            default => self::Critical,
        };
    }

    public function isAlertBand(): bool
    {
        return in_array($this, [self::High, self::Critical], true);
    }
}
