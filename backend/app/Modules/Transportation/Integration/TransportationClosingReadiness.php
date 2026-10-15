<?php

namespace App\Modules\Transportation\Integration;

use App\Modules\SeasonPilgrims\Contracts\SeasonClosingReadinessContributor;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Readiness\ReadinessResult;
use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\MovementStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\ReviewSeverity;
use App\Modules\Transportation\Enums\ReviewStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\PassengerEntitlementStatuses;

/**
 * ADR-0018 T10 (BUSINESS_RULES §15): Transportation contribution to the
 * season closing readiness report.
 */
final class TransportationClosingReadiness implements SeasonClosingReadinessContributor
{
    public function __construct(private readonly PassengerEntitlementStatuses $statuses) {}

    public function evaluate(Season $season): array
    {
        $results = [];

        $movementsInProgress = TransportMovement::query()->where('season_id', $season->id)->where('status', MovementStatus::InProgress->value)->count();
        $runCounts = TransportVehicleRun::query()
            ->where('season_id', $season->id)
            ->whereIn('status', [RunStatus::Boarding->value, RunStatus::Departed->value])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $reviewCounts = TransportReviewItem::query()
            ->where('season_id', $season->id)
            ->where('status', ReviewStatus::Open->value)
            ->selectRaw('severity, count(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity');
        $movementsPlanned = TransportMovement::query()->where('season_id', $season->id)->where('status', MovementStatus::Planned->value)->count();
        $overrides = TransportRunPassenger::query()
            ->where('season_id', $season->id)
            ->where('entitlement_override', true)
            ->where('status', '!=', PassengerStatus::Cancelled->value)
            ->count();

        $boarding = (int) ($runCounts[RunStatus::Boarding->value] ?? 0);
        $departed = (int) ($runCounts[RunStatus::Departed->value] ?? 0);
        $critical = (int) ($reviewCounts[ReviewSeverity::Critical->value] ?? 0);
        $nonCritical = (int) ($reviewCounts[ReviewSeverity::NonCritical->value] ?? 0);
        $mismatches = $this->liveMismatches($season);

        if ($movementsInProgress > 0) {
            $results[] = ReadinessResult::blocking('TRANSPORT_MOVEMENTS_IN_PROGRESS', 'توجد حركات نقل قيد التنفيذ.', $movementsInProgress);
        }
        if ($boarding > 0) {
            $results[] = ReadinessResult::blocking('TRANSPORT_RUNS_BOARDING', 'توجد رحلات في مرحلة الصعود.', $boarding);
        }
        if ($departed > 0) {
            $results[] = ReadinessResult::blocking('TRANSPORT_RUNS_DEPARTED', 'توجد رحلات منطلقة لم تصل.', $departed);
        }
        if ($critical > 0) {
            $results[] = ReadinessResult::blocking('TRANSPORT_CRITICAL_REVIEWS_OPEN', 'توجد بنود مراجعة حرجة مفتوحة في النقل.', $critical);
        }
        if ($movementsPlanned > 0) {
            $results[] = ReadinessResult::warning('TRANSPORT_MOVEMENTS_NOT_EXECUTED', 'توجد حركات نقل مخططة لم تُنفذ.', $movementsPlanned);
        }
        if ($nonCritical > 0) {
            $results[] = ReadinessResult::warning('TRANSPORT_NON_CRITICAL_REVIEWS_OPEN', 'توجد بنود مراجعة غير حرجة مفتوحة في النقل.', $nonCritical);
        }
        if ($overrides > 0) {
            $results[] = ReadinessResult::warning('TRANSPORT_ENTITLEMENT_OVERRIDES', 'توجد تجاوزات لاستحقاق النقل.', $overrides);
        }
        if ($mismatches > 0) {
            $results[] = ReadinessResult::warning('TRANSPORT_ENTITLEMENT_MISMATCHES', 'يوجد ركاب لا تطابق مركباتهم استحقاق برامجهم.', $mismatches);
        }

        if ($results === []) {
            $results[] = ReadinessResult::ok('TRANSPORT_OPERATIONS_SETTLED', 'لا توجد عمليات نقل معلقة.');
        }

        return $results;
    }

    private function liveMismatches(Season $season): int
    {
        $passengers = TransportRunPassenger::query()
            ->where('season_id', $season->id)
            ->where('entitlement_override', false)
            ->whereIn('status', PassengerStatus::occupyingValues())
            ->get(['id', 'vehicle_run_id', 'season_pilgrim_id', 'entitlement_override']);

        $this->statuses->annotate($passengers);

        return $passengers
            ->filter(fn (TransportRunPassenger $passenger): bool => $passenger->getAttribute('live_entitlement_status') === EntitlementStatus::Mismatch->value)
            ->count();
    }
}
