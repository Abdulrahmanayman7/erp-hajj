<?php

namespace App\Modules\ExecutiveAlerts\Actions;

use App\Models\User;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertSeverity;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertVisibility;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\Gate;

/**
 * Live (open + acknowledged) alerts of an explicit season. Unknown / foreign season
 * → 404 (tenant-scoped lookup), exactly like the executive dashboard.
 */
final class ListExecutiveAlerts
{
    public const LIST_LIMIT = 200;

    /**
     * @return array{season: Season, alerts: list<ExecutiveAlert>}
     */
    public function execute(User $actor, int $seasonId): array
    {
        Gate::forUser($actor)->authorize('viewExecutiveAlerts');

        $season = Season::query()->whereKey($seasonId)->firstOrFail();

        /** @var list<ExecutiveAlert> $alerts */
        $alerts = ExecutiveAlertVisibility::query($actor)
            ->where('season_id', $season->id)
            ->whereIn('status', ExecutiveAlertStatus::liveValues())
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->all();

        return ['season' => $season, 'alerts' => $alerts];
    }

    /**
     * Compact summary composed into the executive dashboard (requires `executive_alerts.view`).
     *
     * @return array{open_count: int, acknowledged_count: int, critical_count: int, items: list<ExecutiveAlert>}
     */
    public function summary(User $actor, Season $season, int $itemLimit = 5): array
    {
        $base = fn () => ExecutiveAlertVisibility::query($actor)->where('season_id', $season->id);

        $open = $base()->where('status', ExecutiveAlertStatus::Open->value)->count();
        $ack = $base()->where('status', ExecutiveAlertStatus::Acknowledged->value)->count();
        $critical = $base()
            ->whereIn('status', ExecutiveAlertStatus::liveValues())
            ->where('severity', ExecutiveAlertSeverity::Critical->value)
            ->count();

        /** @var list<ExecutiveAlert> $items */
        $items = $base()
            ->where('status', ExecutiveAlertStatus::Open->value)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit($itemLimit)
            ->get()
            ->all();

        return [
            'open_count' => $open,
            'acknowledged_count' => $ack,
            'critical_count' => $critical,
            'items' => $items,
        ];
    }
}
