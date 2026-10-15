<?php

namespace App\Modules\SeasonPilgrims\Readiness;

use App\Modules\SeasonPilgrims\Contracts\SeasonClosingReadinessContributor;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * Foundation contributor: reports participations that are not settled
 * (Registered / Active) as WARNING. Whether these should BLOCK closing is
 * not decided, so this contributor never blocks.
 */
final class ParticipationClosingReadiness implements SeasonClosingReadinessContributor
{
    public function evaluate(Season $season): array
    {
        $counts = SeasonPilgrim::query()
            ->where('season_id', $season->id)
            ->whereIn('status', [SeasonPilgrimStatus::Registered->value, SeasonPilgrimStatus::Active->value])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $results = [];
        $registered = (int) ($counts[SeasonPilgrimStatus::Registered->value] ?? 0);
        $active = (int) ($counts[SeasonPilgrimStatus::Active->value] ?? 0);

        if ($registered > 0) {
            $results[] = ReadinessResult::warning('SEASON_HAS_REGISTERED_PILGRIMS', 'توجد مشاركات حجاج بحالة مسجل.', $registered);
        }

        if ($active > 0) {
            $results[] = ReadinessResult::warning('SEASON_HAS_ACTIVE_PILGRIMS', 'توجد مشاركات حجاج نشطة لم تكتمل.', $active);
        }

        if ($results === []) {
            $results[] = ReadinessResult::ok('SEASON_PARTICIPATIONS_SETTLED', 'جميع مشاركات الحجاج مكتملة أو ملغاة.');
        }

        return $results;
    }
}
