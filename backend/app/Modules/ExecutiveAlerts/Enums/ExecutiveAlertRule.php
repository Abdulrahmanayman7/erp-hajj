<?php

namespace App\Modules\ExecutiveAlerts\Enums;

/**
 * Approved S10 alert rules only. Occupancy (ALT-OCC-90), planned-progress lag
 * (ALT-PROJ-LAG) and complaints SLA (ALT-COMP-SLA) are DEFERRED_BLOCKED
 * (U01/U02/U03 = C): they have no approved formula and MUST NOT be added here.
 */
enum ExecutiveAlertRule: string
{
    case RiskHigh = 'ALT-RISK-HIGH';
    case ProcurementOverdue = 'ALT-PROC-OVERDUE';

    /**
     * Domain permission required to see (and acknowledge) alerts of this rule.
     */
    public function domainPermission(): string
    {
        return match ($this) {
            self::RiskHigh => 'risks.view',
            self::ProcurementOverdue => 'purchase_requests.view',
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
