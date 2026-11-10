<?php

namespace App\Modules\ExecutiveDashboard\Actions;

use App\Models\User;
use App\Modules\ExecutiveAlerts\Actions\ListExecutiveAlerts;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\OccupancyMetricCalculator;
use App\Modules\Gps\Support\ComposeExecutiveGpsPanel;
use App\Modules\OperationalKpis\Actions\GetOperationalKpis;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * S10 orchestrator: dual page gate → reuse S09 GetOperationalKpis in-process
 * (no HTTP hop, no formula fork) → optional GPS last-known markers (G3; never SIMULATED).
 */
final class GetExecutiveDashboard
{
    public function __construct(
        private readonly GetOperationalKpis $getOperationalKpis,
        private readonly ListExecutiveAlerts $alerts,
        private readonly ComposeExecutiveGpsPanel $gpsPanel,
        private readonly OccupancyMetricCalculator $occupancy,
    ) {}

    /**
     * @return array{
     *   kpis: list<KpiDto>,
     *   season: Season,
     *   response_state: string,
     *   generated_at: string,
     *   timezone: string,
     *   panels: array{
     *     season: array{id: int, name: string, status: string},
     *     accommodation?: array{kpis: list<KpiDto>},
     *     projects?: array{kpis: list<KpiDto>},
     *     alerts?: array{open_count: int, acknowledged_count: int, critical_count: int, items: list<ExecutiveAlert>}
     *   },
     *   gps?: array{freshness_class: string, markers: list<array{transport_season_vehicle_id: int, lat: float, lng: float, freshness_class: string, recorded_at: string}>}
     * }
     */
    public function execute(User $actor, int $seasonId): array
    {
        Gate::forUser($actor)->authorize('viewExecutiveDashboard');

        $kpiPayload = $this->getOperationalKpis->execute($actor, $seasonId);
        /** @var list<KpiDto> $kpis */
        $kpis = $kpiPayload['kpis'];
        /** @var Season $season */
        $season = $kpiPayload['season'];

        $panels = $this->composePanels($season, $kpis, (string) $kpiPayload['generated_at']);

        // A4: alerts summary only for holders of executive_alerts.view (U05 = A), domain-filtered.
        if ($actor->hasPermission('executive_alerts.view')) {
            $panels['alerts'] = $this->alerts->summary($actor, $season);
        }

        $payload = [
            'kpis' => $kpis,
            'season' => $season,
            'response_state' => $kpiPayload['response_state'],
            'generated_at' => $kpiPayload['generated_at'],
            'timezone' => $kpiPayload['timezone'],
            'panels' => $panels,
        ];

        if ($actor->hasPermission('gps.map.view')) {
            $payload['gps'] = $this->gpsPanel->compose($actor, $season);
        }

        return $payload;
    }

    /**
     * A3 presentation composition: pick existing S09 KPI DTOs only (no formula fork).
     * Unauthorized domains are already omitted from $kpis by GetOperationalKpis.
     *
     * @param  list<KpiDto>  $kpis
     * @return array{
     *   season: array{id: int, name: string, status: string},
     *   accommodation?: array{kpis: list<KpiDto>, occupancy_percent: float|null, occupancy_state: string, occupancy_unavailable_reason: string|null, occupancy_denominator: int|null},
     *   projects?: array{kpis: list<KpiDto>}
     * }
     */
    private function composePanels(Season $season, array $kpis, string $generatedAt): array
    {
        $byId = [];
        foreach ($kpis as $kpi) {
            $byId[$kpi->id] = $kpi;
        }

        $panels = [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'status' => $season->status->value,
            ],
        ];

        $accommodation = array_values(array_filter([
            $byId['A02'] ?? null,
            $byId['A03'] ?? null,
        ]));
        if ($accommodation !== []) {
            $metric = $this->occupancy->calculate($season, Carbon::parse($generatedAt));
            $panels['accommodation'] = [
                'kpis' => $accommodation,
                'occupancy_percent' => $metric['occupancy_percent'],
                'occupancy_state' => $metric['state'],
                'occupancy_unavailable_reason' => $metric['unavailable_reason'],
                'occupancy_denominator' => $metric['denominator'],
            ];
        }

        $projects = array_values(array_filter([
            $byId['J07'] ?? null,
            $byId['J09'] ?? null,
        ]));
        if ($projects !== []) {
            $panels['projects'] = ['kpis' => $projects];
        }

        return $panels;
    }
}
