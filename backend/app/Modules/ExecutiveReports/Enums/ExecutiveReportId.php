<?php

namespace App\Modules\ExecutiveReports\Enums;

/**
 * The six approved S10 executive reports (S10-D04=C).
 */
enum ExecutiveReportId: string
{
    case SeasonKpiSummary = 'season-kpi-summary';
    case OperationalAlerts = 'operational-alerts';
    case TransportMovements = 'transport-movements';
    case AccommodationOccupancy = 'accommodation-occupancy';
    case OverduePurchaseApprovals = 'overdue-purchase-approvals';
    case RisksAndComplaints = 'risks-and-complaints';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function domainPermission(): ?string
    {
        return match ($this) {
            self::SeasonKpiSummary, self::OperationalAlerts => null,
            self::TransportMovements => 'transportation.view',
            self::AccommodationOccupancy => 'accommodation.view',
            self::OverduePurchaseApprovals => 'purchase_requests.view',
            // Combined report: service requires risks.view and/or complaints.view (403 if neither).
            self::RisksAndComplaints => null,
        };
    }
}
