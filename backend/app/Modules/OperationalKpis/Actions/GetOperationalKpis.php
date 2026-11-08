<?php

namespace App\Modules\OperationalKpis\Actions;

use App\Core\Tenancy\Exceptions\ConflictingTenantContextException;
use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Exceptions\TenantNotActiveException;
use App\Core\Tenancy\Exceptions\UnauthorizedPlatformContextException;
use App\Models\User;
use App\Modules\Dashboard\Support\DashboardClock;
use App\Modules\OperationalKpis\Calculators\AccommodationKpiCalculator;
use App\Modules\OperationalKpis\Calculators\KpiCalculator;
use App\Modules\OperationalKpis\Calculators\ProcurementKpiCalculator;
use App\Modules\OperationalKpis\Calculators\RisksComplaintsKpiCalculator;
use App\Modules\OperationalKpis\Calculators\SeasonProjectsKpiCalculator;
use App\Modules\OperationalKpis\Calculators\TransportKpiCalculator;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * S09 orchestrator: page gate → season resolve (tenant-scoped, 404 when foreign
 * or unknown) → one clock snapshot → per-domain calculators (authorized KPI ids
 * only, failures isolated per group) → payload. No result caching.
 */
final class GetOperationalKpis
{
    public const RESPONSE_OK = 'ok';

    public const RESPONSE_NO_DOMAIN = 'no_domain';

    /** @var list<KpiCalculator> */
    private readonly array $calculators;

    public function __construct(
        private readonly DashboardClock $clock,
        TransportKpiCalculator $transport,
        AccommodationKpiCalculator $accommodation,
        ProcurementKpiCalculator $procurement,
        SeasonProjectsKpiCalculator $seasonProjects,
        RisksComplaintsKpiCalculator $risksComplaints,
    ) {
        $this->calculators = [$transport, $accommodation, $procurement, $seasonProjects, $risksComplaints];
    }

    /**
     * @return array{
     *   kpis: list<KpiDto>,
     *   season: Season,
     *   response_state: string,
     *   generated_at: string,
     *   timezone: string
     * }
     */
    public function execute(User $actor, int $seasonId): array
    {
        Gate::forUser($actor)->authorize('viewOperationalKpis');

        $season = Season::query()->whereKey($seasonId)->firstOrFail();

        $now = $this->clock->now();

        $kpis = [];

        foreach ($this->calculators as $calculator) {
            $ids = OperationalKpiCatalog::authorizedIdsForGroup($actor, $calculator->group());

            if ($ids === []) {
                continue;
            }

            foreach ($this->calculateGroup($calculator, $season, $now, $ids) as $dto) {
                $kpis[] = $dto;
            }
        }

        return [
            'kpis' => $kpis,
            'season' => $season,
            'response_state' => $kpis === [] ? self::RESPONSE_NO_DOMAIN : self::RESPONSE_OK,
            'generated_at' => $now->toIso8601String(),
            'timezone' => $this->clock->timezone(),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return list<KpiDto>
     */
    private function calculateGroup(KpiCalculator $calculator, Season $season, CarbonInterface $now, array $ids): array
    {
        try {
            return $calculator->calculate($season, $now, $ids);
        } catch (AuthorizationException|AuthenticationException|MissingTenantContextException|InvalidTenantContextException|ConflictingTenantContextException|TenantNotActiveException|UnauthorizedPlatformContextException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Technical failure is isolated to this group; never leak SQL/stack to the client.
            Log::error('operational_kpis.group_failed', [
                'group' => $calculator->group(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return array_map(static fn (string $id): KpiDto => KpiDto::error($id), $ids);
        }
    }
}
