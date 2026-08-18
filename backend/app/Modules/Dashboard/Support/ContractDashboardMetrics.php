<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use Illuminate\Support\Collection;

final class ContractDashboardMetrics
{
    public function __construct(
        private readonly DashboardClock $clock,
    ) {}

    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     today: list<array<string, mixed>>,
     *     expiring_entities: Collection<int, Contract>,
     *     expiring_count: int,
     *     expired_entities: Collection<int, Contract>,
     *     expired_count: int
     * }
     */
    public function build(): array
    {
        $today = $this->clock->today();
        $todayDate = $today->toDateString();
        $days = max(0, (int) config('contracts.expiring_soon_days', 30));
        $until = $today->copy()->addDays($days)->toDateString();

        $executingCount = Contract::query()
            ->where('status', ContractStatus::Executing)
            ->count();

        $expiredQuery = Contract::query()
            ->where('status', ContractStatus::Expired);

        $expiredCount = (clone $expiredQuery)->count();
        $expiredEntities = (clone $expiredQuery)
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $expiringQuery = Contract::query()
            ->where('status', ContractStatus::Executing)
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $todayDate)
            ->where('end_date', '<=', $until);

        $expiringCount = (clone $expiringQuery)->count();
        $expiringEntities = (clone $expiringQuery)
            ->orderBy('end_date')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $expiringList = (clone $expiringQuery)
            ->orderBy('end_date')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Contract $contract): array => $this->compactContract($contract))
            ->all();

        return [
            'kpis' => [
                'contracts_executing' => DashboardKpi::make(
                    $executingCount,
                    'عقود قيد التنفيذ',
                    'info',
                    DashboardLinks::contracts(['status' => 'executing']),
                ),
                'contracts_expiring_soon' => DashboardKpi::make(
                    $expiringCount,
                    'عقود تنتهي قريبًا',
                    'warning',
                    DashboardLinks::contracts(['expiring_soon' => 1]),
                ),
                'contracts_expired' => DashboardKpi::make(
                    $expiredCount,
                    'عقود منتهية',
                    'critical',
                    DashboardLinks::contracts(['status' => 'expired']),
                ),
            ],
            'today' => $expiringList,
            'expiring_entities' => $expiringEntities,
            'expiring_count' => $expiringCount,
            'expired_entities' => $expiredEntities,
            'expired_count' => $expiredCount,
        ];
    }

    /**
     * @return array{id: int, number: string, title: string, end_date: string|null, status: string, href: string}
     */
    private function compactContract(Contract $contract): array
    {
        return [
            'id' => (int) $contract->id,
            'number' => (string) $contract->contract_number,
            'title' => (string) $contract->title,
            'end_date' => $contract->end_date?->toDateString(),
            'status' => $contract->status->value,
            'href' => DashboardLinks::contract((int) $contract->id),
        ];
    }
}
