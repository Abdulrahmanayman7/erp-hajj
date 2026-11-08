<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Core\Tenancy\TenantContext;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\KpiPrecision;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Carbon\CarbonInterface;

/**
 * P01–P08. Every entity is matched on its own non-null `season_id` (D02-Q17);
 * rows without a season never contribute. Per-KPI permissions are enforced by
 * the caller via `$ids`, so a sub-domain the actor cannot see is never queried.
 */
class ProcurementKpiCalculator implements KpiCalculator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function group(): string
    {
        return OperationalKpiCatalog::GROUP_PROCUREMENT;
    }

    public function calculate(Season $season, CarbonInterface $now, array $ids): array
    {
        $results = [];

        if (array_intersect(['P01', 'P02'], $ids) !== []) {
            $row = PurchaseRequest::query()
                ->where('season_id', $season->id)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) AS p01, '
                    .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS p02',
                    [PurchaseRequestStatus::Cancelled->value, PurchaseRequestStatus::Approved->value],
                )
                ->toBase()
                ->first();

            $results['P01'] = in_array('P01', $ids, true) ? KpiDto::count('P01', (int) $row->p01) : null;
            $results['P02'] = in_array('P02', $ids, true) ? KpiDto::count('P02', (int) $row->p02) : null;
        }

        if (in_array('P03', $ids, true)) {
            $results['P03'] = KpiDto::count('P03', ProcurementRfq::query()
                ->where('season_id', $season->id)
                ->whereIn('status', [ProcurementRfqStatus::Published->value, ProcurementRfqStatus::Awarded->value])
                ->count());
        }

        if (array_intersect(['P04', 'P05', 'P06', 'P07', 'P08'], $ids) !== []) {
            $results += $this->purchaseOrderKpis($season, $ids);
        }

        $ordered = [];
        foreach (['P01', 'P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08'] as $id) {
            if (($results[$id] ?? null) instanceof KpiDto) {
                $ordered[] = $results[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, KpiDto>
     */
    private function purchaseOrderKpis(Season $season, array $ids): array
    {
        $tenantId = $this->tenantContext->require()->id;

        $cancelled = PurchaseOrderStatus::Cancelled->value;
        $pipeline = [
            PurchaseOrderStatus::Issued->value,
            PurchaseOrderStatus::PartiallyReceived->value,
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::Closed->value,
        ];
        $fulfilledStatuses = [PurchaseOrderStatus::Received->value, PurchaseOrderStatus::Closed->value];
        $pendingStatuses = [PurchaseOrderStatus::Issued->value, PurchaseOrderStatus::PartiallyReceived->value];

        // Set-based line predicates; explicit tenant predicate on raw SQL (tenancy rule).
        $hasLine = 'EXISTS (SELECT 1 FROM purchase_order_lines pol WHERE pol.purchase_order_id = purchase_orders.id AND pol.tenant_id = ?)';
        $hasShortLine = 'EXISTS (SELECT 1 FROM purchase_order_lines pol WHERE pol.purchase_order_id = purchase_orders.id AND pol.tenant_id = ? AND pol.quantity_received < pol.quantity_ordered)';
        $noShortLine = 'NOT EXISTS (SELECT 1 FROM purchase_order_lines pol WHERE pol.purchase_order_id = purchase_orders.id AND pol.tenant_id = ? AND pol.quantity_received < pol.quantity_ordered)';

        $query = PurchaseOrder::query()->where('season_id', $season->id);

        if (in_array('P04', $ids, true)) {
            $query->selectRaw('COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) AS p04', [$cancelled]);
        }
        if (in_array('P05', $ids, true)) {
            $query->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END), 0) AS p05',
                $pipeline,
            );
        }
        if (in_array('P06', $ids, true)) {
            $query->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN (?, ?) AND {$hasLine} AND {$noShortLine} THEN 1 ELSE 0 END), 0) AS p06",
                [...$fulfilledStatuses, $tenantId, $tenantId],
            );
        }
        if (in_array('P08', $ids, true)) {
            $query->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN (?, ?) AND {$hasShortLine} THEN 1 ELSE 0 END), 0) AS p08",
                [...$pendingStatuses, $tenantId],
            );
        }
        if (in_array('P07', $ids, true)) {
            // SoR = PO lines only (never GRN lines); all POs of the season.
            $query->selectSub(
                PurchaseOrderLine::query()
                    ->selectRaw('COALESCE(SUM(quantity_received), 0)')
                    ->whereIn(
                        'purchase_order_id',
                        PurchaseOrder::query()->select('id')->where('season_id', $season->id),
                    ),
                'p07',
            );
        }

        $row = $query->toBase()->first();

        $results = [];

        foreach (['P04', 'P05', 'P06', 'P08'] as $id) {
            if (in_array($id, $ids, true)) {
                $results[$id] = KpiDto::count($id, (int) $row->{strtolower($id)});
            }
        }

        if (in_array('P07', $ids, true)) {
            $results['P07'] = KpiDto::quantity('P07', KpiPrecision::toDecimalString($row->p07));
        }

        return $results;
    }
}
