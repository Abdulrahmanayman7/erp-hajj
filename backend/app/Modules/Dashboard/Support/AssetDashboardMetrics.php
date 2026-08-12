<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;

final class AssetDashboardMetrics
{
    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>
     * }
     */
    public function build(): array
    {
        $available = Asset::query()->where('status', AssetStatus::Available)->count();
        $inUse = Asset::query()->where('status', AssetStatus::InUse)->count();
        $maintenance = Asset::query()->where('status', AssetStatus::Maintenance)->count();

        return [
            'kpis' => [
                'assets_available' => DashboardKpi::make(
                    $available,
                    'أصول متاحة',
                    'info',
                    DashboardLinks::assets(['status' => 'available']),
                ),
                'assets_in_use' => DashboardKpi::make(
                    $inUse,
                    'أصول قيد الاستخدام',
                    'info',
                    DashboardLinks::assets(['status' => 'in_use']),
                ),
                'assets_maintenance' => DashboardKpi::make(
                    $maintenance,
                    'أصول في الصيانة',
                    'warning',
                    DashboardLinks::assets(['status' => 'maintenance']),
                ),
            ],
        ];
    }
}
