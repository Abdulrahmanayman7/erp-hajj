<?php

namespace App\Modules\SeasonPilgrims\Readiness;

use App\Modules\SeasonPilgrims\Contracts\SeasonClosingReadinessContributor;
use App\Modules\SeasonPilgrims\Enums\ReadinessLevel;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Container\Container;

/**
 * Aggregates every tagged SeasonClosingReadinessContributor (F34).
 */
final class SeasonClosingReadiness
{
    public const CONTRIBUTOR_TAG = 'season_pilgrims.closing_readiness';

    public function __construct(private readonly Container $container) {}

    /**
     * @return array{ready: bool, results: list<array{level: string, code: string, message: string, count: int|null}>}
     */
    public function evaluate(Season $season): array
    {
        $results = [];
        $blocked = false;

        /** @var SeasonClosingReadinessContributor $contributor */
        foreach ($this->container->tagged(self::CONTRIBUTOR_TAG) as $contributor) {
            foreach ($contributor->evaluate($season) as $result) {
                $blocked = $blocked || $result->level === ReadinessLevel::Blocking;
                $results[] = $result->toArray();
            }
        }

        return [
            'ready' => ! $blocked,
            'results' => $results,
        ];
    }
}
