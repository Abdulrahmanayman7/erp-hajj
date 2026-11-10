<?php

namespace App\Modules\ExecutiveReports\Support;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Resources\ExecutiveAlertResource;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertVisibility;
use App\Modules\ExecutiveAlerts\Support\OccupancyMetricCalculator;
use App\Modules\ExecutiveReports\Enums\ExecutiveReportId;
use App\Modules\OperationalKpis\Actions\GetOperationalKpis;
use App\Modules\OperationalKpis\Resources\OperationalKpiResource;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports\CsvEncoder;
use App\Modules\Transportation\Models\TransportMovement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the six S10 executive reports for an explicit season.
 * Domain-gated reports return 403 without the matching .view permission (S09 omit spirit).
 */
final class ExecutiveReportsService
{
    public function __construct(
        private readonly GetOperationalKpis $getOperationalKpis,
        private readonly CsvEncoder $csv,
        private readonly AuthorizationSecurity $security,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $actor, ExecutiveReportId $report, int $seasonId): array
    {
        Gate::forUser($actor)->authorize('viewExecutiveReports');
        $this->assertDomainAccess($actor, $report);

        $season = Season::query()->whereKey($seasonId)->firstOrFail();

        return match ($report) {
            ExecutiveReportId::SeasonKpiSummary => $this->seasonKpiSummary($actor, $season),
            ExecutiveReportId::OperationalAlerts => $this->operationalAlerts($actor, $season),
            ExecutiveReportId::TransportMovements => $this->transportMovements($season),
            ExecutiveReportId::AccommodationOccupancy => $this->accommodationOccupancy($actor, $season),
            ExecutiveReportId::OverduePurchaseApprovals => $this->overduePurchaseApprovals($season),
            ExecutiveReportId::RisksAndComplaints => $this->risksAndComplaints($actor, $season),
        };
    }

    public function exportCsv(User $actor, ExecutiveReportId $report, int $seasonId, Request $request): string
    {
        $payload = $this->build($actor, $report, $seasonId);
        [$headers, $rows] = $this->csvMatrix($report, $payload);

        if (count($rows) > CsvEncoder::MAX_ROWS) {
            throw new AuthorizationException('تصدير التقرير يتجاوز الحد الأقصى للصفوف.');
        }

        $csv = $this->csv->encode($headers, $rows);

        $tenant = $this->tenantContext->require();
        $this->security->record(AuthorizationSecurityEvent::EXECUTIVE_REPORT_EXPORTED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'report_id' => $report->value,
            'season_id' => $seasonId,
            'row_count' => count($rows),
        ], $request);

        return $csv;
    }

    private function assertDomainAccess(User $actor, ExecutiveReportId $report): void
    {
        $permission = $report->domainPermission();
        if ($permission !== null && ! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }

        if ($report === ExecutiveReportId::RisksAndComplaints
            && ! $actor->hasPermission('risks.view')
            && ! $actor->hasPermission('complaints.view')) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonKpiSummary(User $actor, Season $season): array
    {
        $kpiPayload = $this->getOperationalKpis->execute($actor, (int) $season->id);
        /** @var list<KpiDto> $kpis */
        $kpis = $kpiPayload['kpis'];

        $rows = array_map(
            static fn (KpiDto $dto): array => (new OperationalKpiResource($dto))->resolve(),
            $kpis,
        );

        return [
            'report' => ExecutiveReportId::SeasonKpiSummary->value,
            'season_id' => $season->id,
            'generated_at' => $kpiPayload['generated_at'],
            'timezone' => $kpiPayload['timezone'],
            'response_state' => $kpiPayload['response_state'],
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalAlerts(User $actor, Season $season): array
    {
        // Domain-filtered live rows only — does not require executive_alerts.view (report gate is enough).
        $alerts = ExecutiveAlertVisibility::query($actor)
            ->where('season_id', $season->id)
            ->whereIn('status', ExecutiveAlertStatus::liveValues())
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(CsvEncoder::MAX_ROWS)
            ->get()
            ->all();

        $rows = array_map(
            static fn ($alert): array => (new ExecutiveAlertResource($alert))->resolve(),
            $alerts,
        );

        return [
            'report' => ExecutiveReportId::OperationalAlerts->value,
            'season_id' => $season->id,
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transportMovements(Season $season): array
    {
        $movements = TransportMovement::query()
            ->where('season_id', $season->id)
            ->orderBy('id')
            ->limit(CsvEncoder::MAX_ROWS + 1)
            ->get();

        $rows = $movements->take(CsvEncoder::MAX_ROWS)->map(static fn (TransportMovement $m): array => [
            'id' => $m->id,
            'movement_number' => $m->movement_number,
            'status' => $m->status->value,
            'origin_point_id' => $m->origin_point_id,
            'destination_point_id' => $m->destination_point_id,
            'planned_departure_at' => $m->planned_departure_at?->toIso8601String(),
            'planned_arrival_at' => $m->planned_arrival_at?->toIso8601String(),
        ])->values()->all();

        return [
            'report' => ExecutiveReportId::TransportMovements->value,
            'season_id' => $season->id,
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
            'count' => count($rows),
            'truncated' => $movements->count() > CsvEncoder::MAX_ROWS,
        ];
    }

    /**
     * A02/A03 counts plus U01 occupancy % (A03 / Σ effectiveCapacity; never A02).
     *
     * @return array<string, mixed>
     */
    private function accommodationOccupancy(User $actor, Season $season): array
    {
        $kpiPayload = $this->getOperationalKpis->execute($actor, (int) $season->id);
        /** @var list<KpiDto> $kpis */
        $kpis = $kpiPayload['kpis'];
        $byId = [];
        foreach ($kpis as $kpi) {
            $byId[$kpi->id] = (new OperationalKpiResource($kpi))->resolve();
        }

        $a02 = $byId['A02'] ?? null;
        $a03 = $byId['A03'] ?? null;

        $metric = app(OccupancyMetricCalculator::class)->calculate(
            $season,
            Carbon::parse((string) $kpiPayload['generated_at']),
        );

        $row = [
            'a02' => $a02,
            'a03' => $a03,
            'occupancy_percent' => $metric['occupancy_percent'],
            'denominator' => $metric['denominator'],
            'state' => $metric['state'],
            'unavailable_reason' => $metric['unavailable_reason'],
        ];

        return [
            'report' => ExecutiveReportId::AccommodationOccupancy->value,
            'season_id' => $season->id,
            'generated_at' => $kpiPayload['generated_at'],
            'timezone' => $kpiPayload['timezone'],
            'rows' => [$row],
            'count' => 1,
            'occupancy_percent' => $metric['occupancy_percent'],
            'state' => $metric['state'],
            'unavailable_reason' => $metric['unavailable_reason'],
        ];
    }

    /**
     * Pending approval steps with due_at <= now - 7 days for season PRs (U07 = A).
     *
     * @return array<string, mixed>
     */
    private function overduePurchaseApprovals(Season $season): array
    {
        $tenantId = $this->tenantContext->require()->id;
        $cutoff = now()->subDays(ExecutiveAlertScanner::PROCUREMENT_OVERDUE_DAYS);

        $rows = DB::table('purchase_request_approval_steps as s')
            ->join('purchase_request_approval_instances as i', 'i.id', '=', 's.instance_id')
            ->join('purchase_requests as p', 'p.id', '=', 'i.purchase_request_id')
            ->where('s.tenant_id', $tenantId)
            ->where('i.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->where('p.season_id', $season->id)
            ->where('s.status', 'pending')
            ->whereNotNull('s.due_at')
            ->where('s.due_at', '<=', $cutoff)
            ->where('i.status', 'active')
            ->orderBy('s.due_at')
            ->orderBy('s.id')
            ->limit(CsvEncoder::MAX_ROWS)
            ->select([
                's.id as step_id',
                'p.id as purchase_request_id',
                'p.pr_number',
                's.level_order',
                's.due_at',
                's.status as step_status',
            ])
            ->get()
            ->map(static fn ($row): array => [
                'step_id' => (int) $row->step_id,
                'purchase_request_id' => (int) $row->purchase_request_id,
                'pr_number' => (string) $row->pr_number,
                'level_order' => (int) $row->level_order,
                'due_at' => Carbon::parse($row->due_at)->toIso8601String(),
                'step_status' => (string) $row->step_status,
            ])
            ->all();

        return [
            'report' => ExecutiveReportId::OverduePurchaseApprovals->value,
            'season_id' => $season->id,
            'generated_at' => now()->toIso8601String(),
            'overdue_days' => ExecutiveAlertScanner::PROCUREMENT_OVERDUE_DAYS,
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function risksAndComplaints(User $actor, Season $season): array
    {
        $risks = [];
        if ($actor->hasPermission('risks.view')) {
            $risks = OperationalRisk::query()
                ->where('season_id', $season->id)
                ->orderBy('id')
                ->limit(CsvEncoder::MAX_ROWS)
                ->get()
                ->map(static fn (OperationalRisk $risk): array => [
                    'id' => $risk->id,
                    'risk_number' => $risk->risk_number,
                    'title' => $risk->title,
                    'status' => $risk->status->value,
                    'severity_level' => $risk->severity_level?->value,
                    'score' => $risk->score,
                ])
                ->all();
        }

        $complaints = [];
        if ($actor->hasPermission('complaints.view')) {
            $complaints = OperationalComplaint::query()
                ->where('season_id', $season->id)
                ->orderBy('id')
                ->limit(CsvEncoder::MAX_ROWS)
                ->get()
                ->map(static fn (OperationalComplaint $c): array => [
                    'id' => $c->id,
                    'complaint_number' => $c->complaint_number,
                    'title' => $c->title,
                    'status' => $c->status->value,
                    'category_code' => $c->category_code,
                    'escalation_level' => $c->escalation_level,
                ])
                ->all();
        }

        $rows = [];
        foreach ($risks as $risk) {
            $rows[] = array_merge(['entity_type' => 'risk'], $risk);
        }
        foreach ($complaints as $complaint) {
            $rows[] = array_merge(['entity_type' => 'complaint'], $complaint);
        }

        return [
            'report' => ExecutiveReportId::RisksAndComplaints->value,
            'season_id' => $season->id,
            'generated_at' => now()->toIso8601String(),
            'risks' => $risks,
            'complaints' => $complaints,
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function csvMatrix(ExecutiveReportId $report, array $payload): array
    {
        return match ($report) {
            ExecutiveReportId::SeasonKpiSummary => $this->kpiCsv($payload),
            ExecutiveReportId::OperationalAlerts => $this->alertsCsv($payload),
            ExecutiveReportId::TransportMovements => $this->transportCsv($payload),
            ExecutiveReportId::AccommodationOccupancy => $this->occupancyCsv($payload),
            ExecutiveReportId::OverduePurchaseApprovals => $this->overdueCsv($payload),
            ExecutiveReportId::RisksAndComplaints => $this->risksComplaintsCsv($payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function kpiCsv(array $payload): array
    {
        $headers = ['id', 'domain', 'label', 'value', 'display_value', 'state', 'href'];
        $rows = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = [
                $row['id'] ?? null,
                $row['domain'] ?? null,
                $row['label'] ?? null,
                $row['value'] ?? null,
                $row['display_value'] ?? null,
                $row['state'] ?? null,
                $row['href'] ?? null,
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function alertsCsv(array $payload): array
    {
        $headers = ['id', 'rule_code', 'status', 'severity', 'title', 'body', 'opened_at'];
        $rows = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = [
                $row['id'] ?? null,
                $row['rule_code'] ?? null,
                $row['status'] ?? null,
                $row['severity'] ?? null,
                $row['title'] ?? null,
                $row['body'] ?? null,
                $row['opened_at'] ?? null,
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function transportCsv(array $payload): array
    {
        $headers = ['id', 'movement_number', 'status', 'origin_point_id', 'destination_point_id', 'planned_departure_at', 'planned_arrival_at'];
        $rows = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = [
                $row['id'] ?? null,
                $row['movement_number'] ?? null,
                $row['status'] ?? null,
                $row['origin_point_id'] ?? null,
                $row['destination_point_id'] ?? null,
                $row['planned_departure_at'] ?? null,
                $row['planned_arrival_at'] ?? null,
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function occupancyCsv(array $payload): array
    {
        $headers = ['a02_value', 'a03_value', 'denominator', 'occupancy_percent', 'state', 'unavailable_reason'];
        $row = $payload['rows'][0] ?? [];
        $rows = [[
            $row['a02']['value'] ?? null,
            $row['a03']['value'] ?? null,
            $row['denominator'] ?? null,
            $row['occupancy_percent'] ?? null,
            $row['state'] ?? 'unavailable',
            $row['unavailable_reason'] ?? null,
        ]];

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function overdueCsv(array $payload): array
    {
        $headers = ['step_id', 'purchase_request_id', 'pr_number', 'level_order', 'due_at', 'step_status'];
        $rows = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = [
                $row['step_id'] ?? null,
                $row['purchase_request_id'] ?? null,
                $row['pr_number'] ?? null,
                $row['level_order'] ?? null,
                $row['due_at'] ?? null,
                $row['step_status'] ?? null,
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function risksComplaintsCsv(array $payload): array
    {
        $headers = ['entity_type', 'number', 'title', 'status', 'extra'];
        $rows = [];
        foreach ($payload['risks'] as $risk) {
            $rows[] = [
                'risk',
                $risk['risk_number'] ?? null,
                $risk['title'] ?? null,
                $risk['status'] ?? null,
                $risk['severity_level'] ?? null,
            ];
        }
        foreach ($payload['complaints'] as $complaint) {
            $rows[] = [
                'complaint',
                $complaint['complaint_number'] ?? null,
                $complaint['title'] ?? null,
                $complaint['status'] ?? null,
                $complaint['category_code'] ?? null,
            ];
        }

        return [$headers, $rows];
    }
}
