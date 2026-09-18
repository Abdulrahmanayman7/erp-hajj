<?php

namespace App\Modules\Dashboard\Support;

final class DashboardKpi
{
    /**
     * @return array{value: int, label: string, severity: string, href: string}
     */
    public static function make(int $value, string $label, string $severity, string $href): array
    {
        return [
            'value' => max(0, $value),
            'label' => $label,
            'severity' => $severity,
            'href' => $href,
        ];
    }
}
