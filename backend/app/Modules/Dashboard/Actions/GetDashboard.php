<?php

namespace App\Modules\Dashboard\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Dashboard\Support\AssetDashboardMetrics;
use App\Modules\Dashboard\Support\AttentionBuilder;
use App\Modules\Dashboard\Support\ContractDashboardMetrics;
use App\Modules\Dashboard\Support\CustodyDashboardMetrics;
use App\Modules\Dashboard\Support\DashboardClock;
use App\Modules\Dashboard\Support\DashboardLinks;
use App\Modules\Dashboard\Support\DecisionDashboardMetrics;
use App\Modules\Dashboard\Support\InventoryDashboardMetrics;
use App\Modules\Dashboard\Support\MeetingDashboardMetrics;
use App\Modules\Dashboard\Support\TaskDashboardMetrics;
use App\Modules\Employees\Models\Employee;
use App\Modules\Notifications\Actions\CountUnreadNotifications;

final class GetDashboard
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DashboardClock $clock,
        private readonly TaskDashboardMetrics $tasks,
        private readonly ContractDashboardMetrics $contracts,
        private readonly MeetingDashboardMetrics $meetings,
        private readonly DecisionDashboardMetrics $decisions,
        private readonly InventoryDashboardMetrics $inventory,
        private readonly AssetDashboardMetrics $assets,
        private readonly CustodyDashboardMetrics $custodies,
        private readonly AttentionBuilder $attention,
        private readonly CountUnreadNotifications $unreadNotifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $actor): array
    {
        $this->tenantContext->require();

        $sections = [];
        $kpis = [];
        $today = [];
        $work = [];
        $resources = [];
        $bundle = [];

        $employeeId = Employee::query()
            ->where('user_id', $actor->id)
            ->value('id');
        $employeeId = $employeeId !== null ? (int) $employeeId : null;

        if ($actor->hasPermission('tasks.view')) {
            $sections[] = 'tasks';
            $taskMetrics = $this->tasks->build($employeeId);
            $kpis = array_merge($kpis, $taskMetrics['kpis']);
            $today['tasks_due_today'] = $taskMetrics['today'];
            if ($taskMetrics['my_tasks'] !== null) {
                $work['my_tasks'] = $taskMetrics['my_tasks'];
            }
            $bundle['tasks'] = $taskMetrics;
        }

        if ($actor->hasPermission('contracts.view')) {
            $sections[] = 'contracts';
            $contractMetrics = $this->contracts->build();
            $kpis = array_merge($kpis, $contractMetrics['kpis']);
            $today['contracts_expiring'] = $contractMetrics['today'];
            $bundle['contracts'] = $contractMetrics;
        }

        if ($actor->hasPermission('meetings.view')) {
            $sections[] = 'meetings';
            $meetingMetrics = $this->meetings->build();
            $kpis = array_merge($kpis, $meetingMetrics['kpis']);
            $today['meetings_today'] = $meetingMetrics['meetings_today'];
            $today['meetings_upcoming_7d'] = $meetingMetrics['meetings_upcoming_7d'];
            $bundle['meetings'] = $meetingMetrics;
        }

        if ($actor->hasPermission('decisions.view')) {
            $sections[] = 'decisions';
            $decisionMetrics = $this->decisions->build($actor->hasPermission('tasks.view'));
            $kpis = array_merge($kpis, $decisionMetrics['kpis']);
            $bundle['decisions'] = $decisionMetrics;
        }

        if ($actor->hasPermission('inventory.view')) {
            $sections[] = 'inventory';
            $inventoryMetrics = $this->inventory->build();
            $kpis = array_merge($kpis, $inventoryMetrics['kpis']);
            $bundle['inventory'] = $inventoryMetrics;
        }

        if ($actor->hasPermission('assets.view')) {
            $sections[] = 'assets';
            $assetMetrics = $this->assets->build();
            $custodyMetrics = $this->custodies->build($employeeId);
            $kpis = array_merge($kpis, $assetMetrics['kpis'], $custodyMetrics['kpis']);
            if ($custodyMetrics['my_custodies'] !== null) {
                $resources['my_custodies'] = $custodyMetrics['my_custodies'];
            }
            $bundle['custodies'] = $custodyMetrics;
        }

        $sections[] = 'notifications';
        $unread = $this->unreadNotifications->execute($actor);

        return [
            'meta' => [
                'generated_at' => $this->clock->generatedAtIso(),
                'timezone' => $this->clock->timezone(),
                'sections' => $sections,
            ],
            'kpis' => $kpis,
            'attention' => $this->attention->build($bundle),
            'today' => $today,
            'work' => $work,
            'resources' => $resources,
            'notifications' => [
                'unread_count' => $unread,
                'href' => DashboardLinks::notifications(),
            ],
        ];
    }
}
