<?php

namespace App\Modules\ExecutiveAlerts\Enums;

/**
 * Approved S10 alert rules (incl. U01/U02/U03 Owner approval 2026-11-10).
 *
 * @see docs/09-modules/23-executive-dashboard/S10_U01_U02_U03_OWNER_APPROVAL_2026-11-10.md
 */
enum ExecutiveAlertRule: string
{
    case RiskHigh = 'ALT-RISK-HIGH';
    case ProcurementOverdue = 'ALT-PROC-OVERDUE';
    case OccupancyHigh = 'ALT-OCC-90';
    case ProjectLag = 'ALT-PROJ-LAG';
    case ComplaintSla = 'ALT-COMP-SLA';

    /**
     * Domain permission required to see (and acknowledge) alerts of this rule.
     */
    public function domainPermission(): string
    {
        return match ($this) {
            self::RiskHigh => 'risks.view',
            self::ProcurementOverdue => 'purchase_requests.view',
            self::OccupancyHigh => 'accommodation.view',
            self::ProjectLag => 'season_projects.view',
            self::ComplaintSla => 'complaints.view',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $rule): string => $rule->value, self::cases());
    }
}
