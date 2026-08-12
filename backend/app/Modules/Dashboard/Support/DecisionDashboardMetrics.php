<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

final class DecisionDashboardMetrics
{
    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     pending_entities: Collection<int, Decision>,
     *     pending_count: int
     * }
     */
    public function build(bool $includeOpenTasksKpi): array
    {
        $pendingQuery = Decision::query()
            ->where('status', DecisionStatus::PendingApproval);

        $pendingCount = (clone $pendingQuery)->count();
        $pendingEntities = (clone $pendingQuery)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $approvedOpenCount = Decision::query()
            ->where('status', DecisionStatus::Approved)
            ->count();

        $kpis = [
            'decisions_pending_approval' => DashboardKpi::make(
                $pendingCount,
                'قرارات بانتظار الموافقة',
                'warning',
                DashboardLinks::decisions(['status' => 'pending_approval']),
            ),
            'decisions_approved_open' => DashboardKpi::make(
                $approvedOpenCount,
                'قرارات معتمدة',
                'info',
                DashboardLinks::decisions(['status' => 'approved']),
            ),
        ];

        if ($includeOpenTasksKpi) {
            $open = array_map(
                static fn (TaskStatus $s): string => $s->value,
                TaskStatus::openStatuses(),
            );

            $withOpenTasks = Decision::query()
                ->where('status', DecisionStatus::Approved)
                ->whereExists(function ($query) use ($open): void {
                    $query->selectRaw('1')
                        ->from((new Task)->getTable())
                        ->whereColumn('tasks.decision_id', 'decisions.id')
                        ->whereColumn('tasks.tenant_id', 'decisions.tenant_id')
                        ->whereIn('tasks.status', $open);
                })
                ->count();

            $kpis['decisions_with_open_tasks'] = DashboardKpi::make(
                $withOpenTasks,
                'قرارات بمهام مفتوحة',
                'warning',
                DashboardLinks::decisions(['status' => 'approved']),
            );
        }

        return [
            'kpis' => $kpis,
            'pending_entities' => $pendingEntities,
            'pending_count' => $pendingCount,
        ];
    }
}
