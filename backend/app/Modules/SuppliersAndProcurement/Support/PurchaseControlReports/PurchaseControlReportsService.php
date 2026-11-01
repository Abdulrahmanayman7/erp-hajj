<?php

namespace App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PurchaseControlReportsService
{
    public const MAX_SPAN_DAYS = 366;

    /** @var list<string> */
    private const COMPLIANCE_EVENTS = [
        'PR_ROUTING_BLOCKED',
        'PR_SOD_VIOLATION',
        'PR_NO_ELIGIBLE_FINAL_APPROVER',
        'PR_FINANCIAL_REVIEW_NOT_CLEARED',
        'PR_FINANCIAL_REVIEW_REQUIRED',
        'PR_CONCURRENT_DECISION',
        'PR_CANCEL_APPROVED',
        'PR_ADMIN_RECIPIENTS_EMPTY',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly CsvEncoder $csv,
    ) {}

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     * @return array{from: string, to: string, timezone: string, count: int, avg_hours: float|null, median_hours: float|null, p95_hours: float|null, in_flight_count: int, rows: list<array<string, mixed>>}
     */
    public function approvalLatency(User $actor, array $filters, Request $request, bool $auditView = true): array
    {
        [$from, $to, $tz] = $this->resolveRange($filters);
        $query = $this->basePrQuery($filters)
            ->whereIn('status', [PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::Rejected->value])
            ->whereNotNull('submitted_at')
            ->where(function (Builder $q) use ($from, $to): void {
                $q->whereBetween('updated_at', [$from, $to]);
            });

        $prs = $query->get(['id', 'pr_number', 'status', 'submitted_at', 'updated_at', 'estimated_total_priced', 'has_unpriced_lines']);

        $durations = [];
        $rows = [];
        foreach ($prs as $pr) {
            /** @var PurchaseRequest $pr */
            $submitted = CarbonImmutable::parse($pr->submitted_at);
            $ended = CarbonImmutable::parse($pr->updated_at);
            $hours = round($submitted->diffInSeconds($ended) / 3600, 4);
            $durations[] = $hours;
            $rows[] = [
                'pr_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'status' => $pr->status->value,
                'submitted_at' => $submitted->toIso8601String(),
                'terminal_at' => $ended->toIso8601String(),
                'duration_hours' => $hours,
                'estimated_total_priced' => $pr->estimated_total_priced !== null ? (string) $pr->estimated_total_priced : null,
                'has_unpriced_lines' => (bool) $pr->has_unpriced_lines,
            ];
        }

        $inFlight = $this->basePrQuery($filters)
            ->whereIn('status', [
                PurchaseRequestStatus::Submitted->value,
                PurchaseRequestStatus::UnderReview->value,
            ])
            ->count();

        $payload = [
            'report_id' => 'RPT-V01',
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'timezone' => $tz,
            'count' => count($durations),
            'avg_hours' => $this->avg($durations),
            'median_hours' => $this->percentile($durations, 50),
            'p95_hours' => $this->percentile($durations, 95),
            'in_flight_count' => $inFlight,
            'rows' => $rows,
        ];

        if ($auditView) {
            $this->auditViewed($actor, 'RPT-V01', $filters, count($rows), $request);
        }

        return $payload;
    }

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     * @return array{report_id: string, from: string, to: string, timezone: string, count: int, rows: list<array<string, mixed>>}
     */
    public function overdue(User $actor, array $filters, Request $request, bool $auditView = true): array
    {
        [$from, $to, $tz] = $this->resolveRange($filters);
        $now = CarbonImmutable::now('UTC');

        $steps = PurchaseRequestApprovalStep::query()
            ->where('status', PurchaseRequestApprovalStepStatus::Pending->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereBetween('due_at', [$from, $to])
            ->with(['instance.purchaseRequest'])
            ->orderBy('due_at')
            ->limit(CsvEncoder::MAX_ROWS + 1)
            ->get();

        $rows = [];
        foreach ($steps as $step) {
            /** @var PurchaseRequestApprovalStep $step */
            $pr = $step->instance?->purchaseRequest;
            if ($pr === null) {
                continue;
            }
            if (! $this->prMatchesFilters($pr, $filters)) {
                continue;
            }
            $due = CarbonImmutable::parse($step->due_at);
            $rows[] = [
                'pr_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'level_order' => $step->level_order,
                'due_at' => $due->toIso8601String(),
                'hours_overdue' => round($due->diffInSeconds($now) / 3600, 4),
                'escalated_at' => $step->escalated_at?->toIso8601String(),
            ];
        }

        $payload = [
            'report_id' => 'RPT-V02',
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'timezone' => $tz,
            'count' => count($rows),
            'rows' => $rows,
        ];

        if ($auditView) {
            $this->auditViewed($actor, 'RPT-V02', $filters, count($rows), $request);
        }

        return $payload;
    }

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function outcomes(User $actor, array $filters, Request $request, bool $auditView = true): array
    {
        [$from, $to, $tz] = $this->resolveRange($filters);
        $base = $this->basePrQuery($filters);

        $approved = (clone $base)->where('status', PurchaseRequestStatus::Approved->value)
            ->whereBetween('updated_at', [$from, $to])->count();
        $rejected = (clone $base)->where('status', PurchaseRequestStatus::Rejected->value)
            ->whereBetween('updated_at', [$from, $to])->count();
        $cancelled = (clone $base)->where('status', PurchaseRequestStatus::Cancelled->value)
            ->whereBetween('cancelled_at', [$from, $to])->count();
        $submitted = (clone $base)->where('status', PurchaseRequestStatus::Submitted->value)->count();
        $underReview = (clone $base)->where('status', PurchaseRequestStatus::UnderReview->value)->count();
        $returned = (clone $base)->where('status', PurchaseRequestStatus::Returned->value)->count();

        $pricedSum = (clone $base)->where('status', PurchaseRequestStatus::Approved->value)
            ->whereBetween('updated_at', [$from, $to])
            ->where('has_unpriced_lines', false)
            ->sum('estimated_total_priced');

        $unpricedApproved = (clone $base)->where('status', PurchaseRequestStatus::Approved->value)
            ->whereBetween('updated_at', [$from, $to])
            ->where('has_unpriced_lines', true)
            ->count();

        $payload = [
            'report_id' => 'RPT-V03',
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'timezone' => $tz,
            'counts' => [
                'approved' => $approved,
                'rejected' => $rejected,
                'cancelled' => $cancelled,
                'returned' => $returned,
                'submitted' => $submitted,
                'under_review' => $underReview,
            ],
            'approved_priced_total' => $pricedSum !== null ? (string) $pricedSum : null,
            'approved_unpriced_count' => $unpricedApproved,
            'rows' => [
                ['status' => 'approved', 'count' => $approved],
                ['status' => 'rejected', 'count' => $rejected],
                ['status' => 'cancelled', 'count' => $cancelled],
                ['status' => 'returned', 'count' => $returned],
                ['status' => 'submitted', 'count' => $submitted],
                ['status' => 'under_review', 'count' => $underReview],
            ],
        ];

        if ($auditView) {
            $this->auditViewed($actor, 'RPT-V03', $filters, array_sum($payload['counts']), $request);
        }

        return $payload;
    }

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function compliance(User $actor, array $filters, Request $request, bool $auditView = true): array
    {
        [$from, $to, $tz] = $this->resolveRange($filters);
        $tenant = $this->tenantContext->require();

        $logs = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('event_type', self::COMPLIANCE_EVENTS)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->limit(CsvEncoder::MAX_ROWS + 1)
            ->get(['id', 'event_type', 'created_at', 'actor_user_id', 'entity_id', 'entity_number', 'reason', 'metadata']);

        $rows = [];
        foreach ($logs as $log) {
            /** @var AuditLog $log */
            $rows[] = [
                'event_type' => $log->event_type,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'actor_id' => $log->actor_user_id,
                'entity_id' => $log->entity_id,
                'entity_number' => $log->entity_number,
                'reason' => $log->reason,
                'error_code' => is_array($log->metadata) ? ($log->metadata['code'] ?? $log->metadata['error_code'] ?? null) : null,
            ];
        }

        $payload = [
            'report_id' => 'RPT-V04',
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'timezone' => $tz,
            'count' => count($rows),
            'rows' => $rows,
        ];

        if ($auditView) {
            $this->auditViewed($actor, 'RPT-V04', $filters, count($rows), $request);
        }

        return $payload;
    }

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     */
    public function exportCsv(User $actor, string $reportId, array $filters, Request $request): string
    {
        $data = match ($reportId) {
            'RPT-V01', 'approval-latency' => $this->approvalLatency($actor, $filters, $request, false),
            'RPT-V02', 'overdue' => $this->overdue($actor, $filters, $request, false),
            'RPT-V03', 'outcomes' => $this->outcomes($actor, $filters, $request, false),
            'RPT-V04', 'compliance' => $this->compliance($actor, $filters, $request, false),
            default => throw SupplierDomainException::reportDateRangeInvalid('تقرير غير معروف.'),
        };

        $normalizedId = match ($reportId) {
            'approval-latency' => 'RPT-V01',
            'overdue' => 'RPT-V02',
            'outcomes' => 'RPT-V03',
            'compliance' => 'RPT-V04',
            default => $reportId,
        };

        [$headers, $rowCount, $csvBody] = $this->buildCsv($normalizedId, $data);

        if ($rowCount > CsvEncoder::MAX_ROWS) {
            throw SupplierDomainException::reportExportTooLarge();
        }

        $tenant = $this->tenantContext->require();
        $this->security->record(AuthorizationSecurityEvent::CONTROL_REPORT_EXPORTED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'report_id' => $normalizedId,
            'filters_hash' => hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)),
            'row_count' => $rowCount,
        ], $request);

        return $csvBody;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: int, 2: string}
     */
    private function buildCsv(string $reportId, array $data): array
    {
        return match ($reportId) {
            'RPT-V01' => $this->latencyCsv($data),
            'RPT-V02' => $this->overdueCsv($data),
            'RPT-V03' => $this->outcomesCsv($data),
            'RPT-V04' => $this->complianceCsv($data),
            default => throw SupplierDomainException::reportDateRangeInvalid('تقرير غير معروف.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: int, 2: string}
     */
    private function latencyCsv(array $data): array
    {
        $headers = ['pr_number', 'status', 'submitted_at', 'terminal_at', 'duration_hours', 'estimated_total_priced', 'has_unpriced_lines'];
        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['pr_number'],
                $row['status'],
                $row['submitted_at'],
                $row['terminal_at'],
                $row['duration_hours'],
                $row['estimated_total_priced'],
                $row['has_unpriced_lines'] ? '1' : '0',
            ];
        }

        return [$headers, count($rows), $this->csv->encode($headers, $rows)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: int, 2: string}
     */
    private function overdueCsv(array $data): array
    {
        $headers = ['pr_number', 'level_order', 'due_at', 'hours_overdue', 'escalated_at'];
        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['pr_number'],
                $row['level_order'],
                $row['due_at'],
                $row['hours_overdue'],
                $row['escalated_at'],
            ];
        }

        return [$headers, count($rows), $this->csv->encode($headers, $rows)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: int, 2: string}
     */
    private function outcomesCsv(array $data): array
    {
        $headers = ['status', 'count', 'approved_priced_total', 'approved_unpriced_count'];
        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['status'],
                $row['count'],
                $data['approved_priced_total'],
                $data['approved_unpriced_count'],
            ];
        }

        return [$headers, count($rows), $this->csv->encode($headers, $rows)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: int, 2: string}
     */
    private function complianceCsv(array $data): array
    {
        $headers = ['event_type', 'occurred_at', 'actor_id', 'entity_id', 'entity_number', 'reason', 'error_code'];
        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['event_type'],
                $row['occurred_at'],
                $row['actor_id'],
                $row['entity_id'],
                $row['entity_number'],
                $row['reason'],
                $row['error_code'],
            ];
        }

        return [$headers, count($rows), $this->csv->encode($headers, $rows)];
    }

    /**
     * @param  array{from: string, to: string, season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(array $filters): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenantContext->require();
        $tz = $tenant->timezone ?: 'Asia/Riyadh';

        try {
            $fromLocal = CarbonImmutable::parse((string) $filters['from'], $tz)->startOfDay();
            $toLocal = CarbonImmutable::parse((string) $filters['to'], $tz)->endOfDay();
        } catch (\Throwable) {
            throw SupplierDomainException::reportDateRangeInvalid();
        }

        if ($toLocal->lessThan($fromLocal)) {
            throw SupplierDomainException::reportDateRangeInvalid();
        }

        $spanDays = $fromLocal->diffInDays($toLocal) + 1;
        if ($spanDays > self::MAX_SPAN_DAYS) {
            throw SupplierDomainException::reportDateRangeInvalid();
        }

        return [$fromLocal->utc(), $toLocal->utc(), $tz];
    }

    /**
     * @param  array{season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     */
    private function basePrQuery(array $filters): Builder
    {
        $query = PurchaseRequest::query();

        if (! empty($filters['season_id'])) {
            $query->where('season_id', (int) $filters['season_id']);
        }
        if (! empty($filters['organization_unit_id'])) {
            $query->where('beneficiary_organization_unit_id', (int) $filters['organization_unit_id']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', (string) $filters['priority']);
        }

        return $query;
    }

    /**
     * @param  array{season_id?: ?int, organization_unit_id?: ?int, priority?: ?string}  $filters
     */
    private function prMatchesFilters(PurchaseRequest $pr, array $filters): bool
    {
        if (! empty($filters['season_id']) && (int) $pr->season_id !== (int) $filters['season_id']) {
            return false;
        }
        if (! empty($filters['organization_unit_id'])
            && (int) $pr->beneficiary_organization_unit_id !== (int) $filters['organization_unit_id']) {
            return false;
        }
        if (! empty($filters['priority']) && (string) $pr->priority->value !== (string) $filters['priority']) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<float>  $values
     */
    private function avg(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 4);
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $rank = (int) ceil(($p / 100) * count($values)) - 1;
        $rank = max(0, min($rank, count($values) - 1));

        return round($values[$rank], 4);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function auditViewed(User $actor, string $reportId, array $filters, int $rowCount, Request $request): void
    {
        $tenant = $this->tenantContext->require();
        $this->security->record(AuthorizationSecurityEvent::CONTROL_REPORT_VIEWED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'report_id' => $reportId,
            'filters_hash' => hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)),
            'row_count' => $rowCount,
        ], $request);
    }
}
