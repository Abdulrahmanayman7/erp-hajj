<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\SeasonPilgrims\Models\Season;
use Carbon\CarbonInterface;

/**
 * One calculator per S09 domain group (D07). Callers pass only the KPI ids the
 * actor is authorized to see; a calculator never computes anything else.
 */
interface KpiCalculator
{
    /** One of the OperationalKpiCatalog::GROUP_* constants. */
    public function group(): string;

    /**
     * @param  list<string>  $ids  Authorized ids of this group (non-empty).
     * @return list<KpiDto>
     */
    public function calculate(Season $season, CarbonInterface $now, array $ids): array;
}
