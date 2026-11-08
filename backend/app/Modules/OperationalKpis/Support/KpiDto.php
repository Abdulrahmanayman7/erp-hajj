<?php

namespace App\Modules\OperationalKpis\Support;

/**
 * Immutable result of one KPI calculation.
 */
final readonly class KpiDto
{
    public function __construct(
        public string $id,
        public int|float|null $value,
        public KpiState $state,
        public ?string $displayValue,
        public string $domain,
        public string $labelAr,
        public ?string $drilldownPath,
    ) {}

    public static function count(string $id, int $value): self
    {
        $meta = OperationalKpiCatalog::definition($id);

        return new self(
            id: $id,
            value: $value,
            state: KpiState::Ok,
            displayValue: KpiPrecision::displayCount($value),
            domain: $meta['domain'],
            labelAr: $meta['label'],
            drilldownPath: $meta['href'],
        );
    }

    /**
     * @param  numeric-string  $quantity  Decimal quantity (P07), up to 4 d.p. of precision.
     */
    public static function quantity(string $id, string $quantity): self
    {
        $meta = OperationalKpiCatalog::definition($id);

        return new self(
            id: $id,
            value: (float) $quantity,
            state: KpiState::Ok,
            displayValue: KpiPrecision::displayQuantity($quantity),
            domain: $meta['domain'],
            labelAr: $meta['label'],
            drilldownPath: $meta['href'],
        );
    }

    /**
     * One-decimal percentage KPI (J07, J09).
     *
     * @param  numeric-string  $percent
     */
    public static function percent(string $id, string $percent): self
    {
        $meta = OperationalKpiCatalog::definition($id);

        return new self(
            id: $id,
            value: (float) $percent,
            state: KpiState::Ok,
            displayValue: KpiPrecision::displayPercent($percent),
            domain: $meta['domain'],
            labelAr: $meta['label'],
            drilldownPath: $meta['href'],
        );
    }

    /** AVG over an empty sample / zero-denominator ratio (D06). */
    public static function undefined(string $id): self
    {
        return self::withState($id, KpiState::Undefined);
    }

    /** Technical failure isolated to the KPI's calculator group. */
    public static function error(string $id): self
    {
        return self::withState($id, KpiState::Error);
    }

    private static function withState(string $id, KpiState $state): self
    {
        $meta = OperationalKpiCatalog::definition($id);

        return new self(
            id: $id,
            value: null,
            state: $state,
            displayValue: null,
            domain: $meta['domain'],
            labelAr: $meta['label'],
            drilldownPath: $meta['href'],
        );
    }
}
