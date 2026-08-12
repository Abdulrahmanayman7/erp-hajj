<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\AssetCustody;
use Illuminate\Support\Collection;

final class CustodyDashboardMetrics
{
    public function __construct(
        private readonly DashboardClock $clock,
    ) {}

    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     my_custodies: array{active: int, overdue: int, href: string}|null,
     *     overdue_entities: Collection<int, AssetCustody>,
     *     overdue_count: int,
     *     due_soon_entities: Collection<int, AssetCustody>,
     *     due_soon_count: int
     * }
     */
    public function build(?int $employeeId): array
    {
        $now = $this->clock->now();
        $dueSoonDays = max(0, (int) config('notifications.custody_expected_return_soon_days', 3));
        $dueSoonUntil = $now->copy()->addDays($dueSoonDays);

        $overdueQuery = AssetCustody::query()
            ->where('status', CustodyStatus::Active)
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', $now);

        $overdueCount = (clone $overdueQuery)->count();
        $overdueEntities = (clone $overdueQuery)
            ->orderBy('expected_return_at')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $dueSoonQuery = AssetCustody::query()
            ->where('status', CustodyStatus::Active)
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '>', $now)
            ->where('expected_return_at', '<=', $dueSoonUntil);

        $dueSoonCount = (clone $dueSoonQuery)->count();
        $dueSoonEntities = (clone $dueSoonQuery)
            ->orderBy('expected_return_at')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $myCustodies = null;
        if ($employeeId !== null) {
            $mine = AssetCustody::query()
                ->where('status', CustodyStatus::Active)
                ->where('employee_id', $employeeId);
            $active = (clone $mine)->count();
            $overdueMine = (clone $mine)
                ->whereNotNull('expected_return_at')
                ->where('expected_return_at', '<', $now)
                ->count();

            $myCustodies = [
                'active' => $active,
                'overdue' => $overdueMine,
                'href' => DashboardLinks::myCustodies(),
            ];
        }

        return [
            'kpis' => [
                'custodies_overdue' => DashboardKpi::make(
                    $overdueCount,
                    'عُهد متأخرة',
                    'critical',
                    DashboardLinks::assets(['overdue' => 1]),
                ),
                'custodies_due_soon' => DashboardKpi::make(
                    $dueSoonCount,
                    'عُهد يقترب موعد إرجاعها',
                    'warning',
                    DashboardLinks::assets(),
                ),
            ],
            'my_custodies' => $myCustodies,
            'overdue_entities' => $overdueEntities,
            'overdue_count' => $overdueCount,
            'due_soon_entities' => $dueSoonEntities,
            'due_soon_count' => $dueSoonCount,
        ];
    }
}
