<?php

namespace App\Modules\ExecutiveAlerts\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertSeverity;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates the approved S10 alert rules for the CURRENT tenant (fail-closed: requires
 * TenantContext) and reconciles the `executive_alerts` table:
 *
 *  - condition true  → idempotent upsert by `(tenant_id, dedupe_key)`; a brand-new or
 *    re-opened (previously cleared) alert notifies domain holders, a refresh never does;
 *  - condition false → live alerts of the scanned rules become `cleared`.
 *
 * Rules (the ONLY ones with an approved formula):
 *  - ALT-RISK-HIGH     risk.status <> closed AND severity ∈ high|critical (`RiskSeverity::isAlertBand`)
 *  - ALT-PROC-OVERDUE  pending approval step with due_at <= now - 7 days (U07 = A)
 *
 * ALT-OCC-90 / ALT-PROJ-LAG / ALT-COMP-SLA are DEFERRED_BLOCKED (U01/U02/U03 = C) and
 * intentionally have no code path here.
 */
final class ExecutiveAlertScanner
{
    /** U07 = A: an approval step is overdue once it is pending and due_at is >= 7 days past. */
    public const PROCUREMENT_OVERDUE_DAYS = 7;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @return array{opened: int, refreshed: int, cleared: int}
     */
    public function scanCurrentTenant(?Carbon $now = null): array
    {
        $this->tenantContext->require();
        $now ??= now();

        $stats = ['opened' => 0, 'refreshed' => 0, 'cleared' => 0];

        $this->mergeStats($stats, $this->scanRiskHigh($now));
        $this->mergeStats($stats, $this->scanProcurementOverdue($now));

        return $stats;
    }

    /**
     * @return array{opened: int, refreshed: int, cleared: int}
     */
    private function scanRiskHigh(Carbon $now): array
    {
        $bands = array_values(array_map(
            static fn (RiskSeverity $s): string => $s->value,
            array_filter(RiskSeverity::cases(), static fn (RiskSeverity $s): bool => $s->isAlertBand()),
        ));

        /** @var array<string, true> $seen */
        $seen = [];
        $stats = ['opened' => 0, 'refreshed' => 0, 'cleared' => 0];

        OperationalRisk::query()
            ->where('status', '<>', RiskStatus::Closed->value)
            ->whereIn('severity_level', $bands)
            ->orderBy('id')
            ->chunkById(200, function ($risks) use ($now, &$seen, &$stats): void {
                foreach ($risks as $risk) {
                    /** @var OperationalRisk $risk */
                    $key = ExecutiveAlertRule::RiskHigh->value.':risk:'.$risk->id;
                    $seen[$key] = true;

                    $severity = $risk->severity_level === RiskSeverity::Critical
                        ? ExecutiveAlertSeverity::Critical
                        : ExecutiveAlertSeverity::Warning;

                    $outcome = $this->upsert(
                        rule: ExecutiveAlertRule::RiskHigh,
                        dedupeKey: $key,
                        seasonId: (int) $risk->season_id,
                        title: 'خطر تشغيلي مرتفع: '.$risk->risk_number,
                        body: mb_substr((string) $risk->title, 0, 500),
                        severity: $severity,
                        sourceType: 'operational_risk',
                        sourceId: (int) $risk->id,
                        meta: [
                            'risk_number' => $risk->risk_number,
                            'severity_level' => $risk->severity_level?->value,
                            'status' => $risk->status->value,
                        ],
                        now: $now,
                        notificationType: NotificationType::ExecutiveAlertRiskHigh,
                        notificationNumber: (string) $risk->risk_number,
                    );
                    $stats[$outcome]++;
                }
            });

        $stats['cleared'] += $this->clearUnseen(ExecutiveAlertRule::RiskHigh, $seen, $now);

        return $stats;
    }

    /**
     * @return array{opened: int, refreshed: int, cleared: int}
     */
    private function scanProcurementOverdue(Carbon $now): array
    {
        $tenantId = $this->tenantContext->require()->id;
        $cutoff = $now->copy()->subDays(self::PROCUREMENT_OVERDUE_DAYS);

        // Raw joins carry an explicit tenant predicate on every tenant-owned table.
        $rows = DB::table('purchase_request_approval_steps as s')
            ->join('purchase_request_approval_instances as i', 'i.id', '=', 's.instance_id')
            ->join('purchase_requests as p', 'p.id', '=', 'i.purchase_request_id')
            ->where('s.tenant_id', $tenantId)
            ->where('i.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->where('s.status', 'pending')
            ->whereNotNull('s.due_at')
            ->where('s.due_at', '<=', $cutoff)
            ->where('i.status', 'active')
            ->whereNotNull('p.season_id')
            ->groupBy('p.id', 'p.season_id', 'p.pr_number')
            ->orderBy('p.id')
            ->selectRaw('p.id as pr_id, p.season_id as season_id, p.pr_number as pr_number, MIN(s.due_at) as oldest_due_at, COUNT(*) as overdue_steps')
            ->get();

        /** @var array<string, true> $seen */
        $seen = [];
        $stats = ['opened' => 0, 'refreshed' => 0, 'cleared' => 0];

        foreach ($rows as $row) {
            $key = ExecutiveAlertRule::ProcurementOverdue->value.':purchase_request:'.$row->pr_id;
            $seen[$key] = true;

            $oldest = Carbon::parse($row->oldest_due_at);

            $outcome = $this->upsert(
                rule: ExecutiveAlertRule::ProcurementOverdue,
                dedupeKey: $key,
                seasonId: (int) $row->season_id,
                title: 'اعتماد طلب شراء متأخر: '.$row->pr_number,
                body: sprintf('خطوة اعتماد معلّقة تجاوزت موعدها بأكثر من %d أيام.', self::PROCUREMENT_OVERDUE_DAYS),
                severity: ExecutiveAlertSeverity::Warning,
                sourceType: 'purchase_request',
                sourceId: (int) $row->pr_id,
                meta: [
                    'pr_number' => $row->pr_number,
                    'oldest_due_at' => $oldest->toIso8601String(),
                    'overdue_steps' => (int) $row->overdue_steps,
                ],
                now: $now,
                notificationType: NotificationType::ExecutiveAlertProcurementOverdue,
                notificationNumber: (string) $row->pr_number,
            );
            $stats[$outcome]++;
        }

        $stats['cleared'] += $this->clearUnseen(ExecutiveAlertRule::ProcurementOverdue, $seen, $now);

        return $stats;
    }

    /**
     * @param  array<string, scalar|null>  $meta
     * @return 'opened'|'refreshed'
     */
    private function upsert(
        ExecutiveAlertRule $rule,
        string $dedupeKey,
        int $seasonId,
        string $title,
        ?string $body,
        ExecutiveAlertSeverity $severity,
        string $sourceType,
        int $sourceId,
        array $meta,
        Carbon $now,
        NotificationType $notificationType,
        string $notificationNumber,
    ): string {
        $alert = ExecutiveAlert::query()->where('dedupe_key', $dedupeKey)->first();

        if ($alert === null) {
            $alert = new ExecutiveAlert;
            $alert->forceFill([
                'season_id' => $seasonId,
                'rule_code' => $rule,
                'dedupe_key' => $dedupeKey,
                'status' => ExecutiveAlertStatus::Open,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'severity' => $severity,
                'source_entity_type' => $sourceType,
                'source_entity_id' => $sourceId,
                'meta' => $meta,
                'opened_at' => $now,
                'last_seen_at' => $now,
            ]);

            try {
                $alert->save();
            } catch (UniqueConstraintViolationException) {
                // Concurrent scanner won the race: treat as a refresh of the existing row.
                $alert = ExecutiveAlert::query()->where('dedupe_key', $dedupeKey)->firstOrFail();

                return $this->refresh($alert, $title, $body, $severity, $meta, $now, $notificationType, $notificationNumber);
            }

            $this->notifyOpened($alert, $notificationType, $notificationNumber);

            return 'opened';
        }

        return $this->refresh($alert, $title, $body, $severity, $meta, $now, $notificationType, $notificationNumber);
    }

    /**
     * @param  array<string, scalar|null>  $meta
     * @return 'opened'|'refreshed'
     */
    private function refresh(
        ExecutiveAlert $alert,
        string $title,
        ?string $body,
        ExecutiveAlertSeverity $severity,
        array $meta,
        Carbon $now,
        NotificationType $notificationType,
        string $notificationNumber,
    ): string {
        $reopened = $alert->status === ExecutiveAlertStatus::Cleared;

        $attributes = [
            'title' => mb_substr($title, 0, 255),
            'body' => $body,
            'severity' => $severity,
            'meta' => $meta,
            'last_seen_at' => $now,
        ];

        if ($reopened) {
            $attributes += [
                'status' => ExecutiveAlertStatus::Open,
                'opened_at' => $now,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'cleared_at' => null,
            ];
        }

        $alert->forceFill($attributes)->save();

        if ($reopened) {
            $this->notifyOpened($alert, $notificationType, $notificationNumber);

            return 'opened';
        }

        return 'refreshed';
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function clearUnseen(ExecutiveAlertRule $rule, array $seen, Carbon $now): int
    {
        $cleared = 0;

        ExecutiveAlert::query()
            ->where('rule_code', $rule->value)
            ->whereIn('status', ExecutiveAlertStatus::liveValues())
            ->orderBy('id')
            ->chunkById(200, function ($alerts) use ($seen, $now, &$cleared): void {
                foreach ($alerts as $alert) {
                    /** @var ExecutiveAlert $alert */
                    if (isset($seen[$alert->dedupe_key])) {
                        continue;
                    }

                    $alert->forceFill([
                        'status' => ExecutiveAlertStatus::Cleared,
                        'cleared_at' => $now,
                    ])->save();
                    $cleared++;
                }
            });

        return $cleared;
    }

    /**
     * U06 = B: notify domain managers (holders of the rule's domain permission) within the tenant only.
     */
    private function notifyOpened(ExecutiveAlert $alert, NotificationType $type, string $number): void
    {
        $holders = $this->recipients->usersWithPermission($alert->rule_code->domainPermission());
        if ($holders === []) {
            return;
        }

        $this->dispatcher->notify(
            type: $type,
            recipientUsers: $holders,
            severity: null,
            entityType: 'executive_alert',
            entityId: (int) $alert->id,
            dedupeBucket: 'opened-'.$alert->opened_at->getTimestamp(),
            context: ['number' => $number],
        );
    }

    /**
     * @param  array{opened: int, refreshed: int, cleared: int}  $into
     * @param  array{opened: int, refreshed: int, cleared: int}  $from
     */
    private function mergeStats(array &$into, array $from): void
    {
        foreach ($from as $k => $v) {
            $into[$k] += $v;
        }
    }
}
