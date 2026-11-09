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
     *   gps?: array{freshness_class: string, markers: list<never>}
     * }
     */
    public function execute(User $actor, int $seasonId): array
    {
        Gate::forUser($actor)->authorize('viewExecutiveDashboard');

        $kpiPayload = $this->getOperationalKpis->execute($actor, $seasonId);

        $payload = [
            'kpis' => $kpiPayload['kpis'],
            'season' => $kpiPayload['season'],
            'response_state' => $kpiPayload['response_state'],
            'generated_at' => $kpiPayload['generated_at'],
            'timezone' => $kpiPayload['timezone'],
        ];

        if ($actor->hasPermission('gps.map.view')) {
            $payload['gps'] = [
                'freshness_class' => 'unavailable',
                'markers' => [],
            ];
        }

        return $payload;
    }
}
