<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;

/**
 * Deterministic weighted scoring (IIC-D2 / design §2.6).
 *
 * total = round_half_up( sum(score_i * weight_i / 100), 2 )
 */
final class EvaluationScoreCalculator
{
    /**
     * @param  list<array{weight: string|float|int, score: string|float|int}>  $lines
     */
    public function total(array $lines): string
    {
        if ($lines === []) {
            throw SupplierDomainException::evaluationWeightsInvalid('0.0000');
        }

        $sum = '0';
        $weightSum = '0';

        foreach ($lines as $line) {
            $weight = $this->normalizeDecimal((string) $line['weight'], 4);
            $score = $this->normalizeDecimal((string) $line['score'], 2);
            $this->assertScoreRange($score);

            $weightSum = bcadd($weightSum, $weight, 4);
            $product = bcmul($score, bcdiv($weight, '100', 8), 8);
            $sum = bcadd($sum, $product, 8);
        }

        if (bccomp($weightSum, '99.99', 4) < 0 || bccomp($weightSum, '100.01', 4) > 0) {
            throw SupplierDomainException::evaluationWeightsInvalid($weightSum);
        }

        $rounded = $this->roundHalfUp($sum, 2);
        if (bccomp($rounded, '0', 2) < 0) {
            return '0.00';
        }
        if (bccomp($rounded, '100', 2) > 0) {
            return '100.00';
        }

        return $rounded;
    }

    public function assertScoreRange(string $score): void
    {
        if (bccomp($score, '0', 2) < 0 || bccomp($score, '100', 2) > 0) {
            throw SupplierDomainException::evaluationScoreOutOfRange();
        }
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        if (! is_numeric($value)) {
            throw SupplierDomainException::evaluationScoreOutOfRange();
        }

        return bcadd($value, '0', $scale);
    }

    private function roundHalfUp(string $value, int $scale): string
    {
        $multiplier = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $multiplier, $scale + 2);
        $adjusted = bcadd($scaled, '0.5', 1);

        return bcdiv(bcdiv($adjusted, '1', 0), $multiplier, $scale);
    }
}
