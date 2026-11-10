<?php

namespace App\Modules\ExecutiveDashboard\Actions;

use App\Models\User;
use App\Modules\OperationalKpis\Actions\GetOperationalKpis;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\Gate;

/**
 * S10 orchestrator: dual page gate → reuse S09 GetOperationalKpis in-process
 * (no HTTP hop, no formula fork) → optional GPS UNAVAILABLE stub.
 */
final class GetExecutiveDashboard
{
    public function __construct(
        private readonly GetOperationalKpis $getOperationalKpis,
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
     *     projects?: array{kpis: list<KpiDto>}
     *   },
     *   gps?: array{freshness_class: string, markers: list<never>}
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

        $payload = [
            'kpis' => $kpis,
            'season' => $season,
            'response_state' => $kpiPayload['response_state'],
            'generated_at' => $kpiPayload['generated_at'],
            'timezone' => $kpiPayload['timezone'],
            'panels' => $this->composePanels($season, $kpis),
        ];

        if ($actor->hasPermission('gps.map.view')) {
            $payload['gps'] = [
                'freshness_class' => 'unavailable',
                'markers' => [],
            ];
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
     *   accommodation?: array{kpis: list<KpiDto>},
     *   projects?: array{kpis: list<KpiDto>}
     * }
     */
    private function composePanels(Season $season, array $kpis): array
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
            $panels['accommodation'] = ['kpis' => $accommodation];
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
