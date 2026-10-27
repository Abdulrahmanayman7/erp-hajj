<?php

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('records a reproducible isolated Phase 3 performance baseline', function (): void {
    if (getenv('PHASE3_BENCHMARK') !== '1') {
        $this->markTestSkipped('Opt-in performance measurement, not a timing assertion.');
    }
    $driver = DB::connection()->getDriverName();
    if ($driver === 'sqlite') {
        expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    } else {
        $identity = DB::selectOne('select database() as db, @@port as port, @@datadir as datadir');
        expect($identity->db)->toBe('erp_hajj_testing');
        expect((int) $identity->port)->toBe(33117);
        expect(str_replace('\\', '/', $identity->datadir))->toContain('/storage/framework/testing/phase3-mysql-');
    }
    [$actor, $season] = accGateCActor([
        'accommodation.view', 'accommodation.draft', 'accommodation.confirm',
        'accommodation.manage_facilities', 'accommodation.manage_season_allocation',
        'accommodation.manage_scopes', 'season_pilgrims.view', 'audit_logs.view',
    ]);
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 200);
    $family = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id, 'name' => 'Synthetic performance family',
    ]));
    $ids = [];
    withTenant($actor->tenant, function () use ($actor, $season, $world, $family, &$ids): void {
        for ($i = 0; $i < 100; $i++) {
            $pilgrim = accGateCPilgrim($actor, $season, familyGroupId: $family->id);
            $ids[] = AccommodationAssignment::query()->create([
                'season_id' => $season->id, 'season_pilgrim_id' => $pilgrim->id,
                'season_unit_id' => $world['season_unit']['id'], 'unit_id' => $world['unit']['id'],
                'booking_status' => 'draft', 'starts_at' => '2027-06-01 00:00:00',
                'original_ends_at' => '2027-06-02 00:00:00', 'effective_ends_at' => '2027-06-02 00:00:00',
                'created_by' => $actor->id,
            ])->id;
        }
        $template = AccommodationAssignment::query()->findOrFail($ids[0])->getAttributes();
        unset($template['id']);
        $history = [];
        for ($i = 0; $i < 1000; $i++) {
            $start = CarbonImmutable::parse('2026-01-01 UTC')->addHours($i);
            $history[] = [...$template, 'booking_status' => 'completed', 'operational_status' => 'checked_out',
                'checkout_source' => 'staff', 'family_group_id' => $family->id,
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'original_ends_at' => $start->addHour()->format('Y-m-d H:i:s'),
                'effective_ends_at' => $start->addHour()->format('Y-m-d H:i:s'),
            ];
        }
        foreach (array_chunk($history, 100) as $chunk) DB::table('accommodation_assignments')->insert($chunk);
    });
    $auditRows = [];
    for ($i = 0; $i < 1000; $i++) {
        $auditRows[] = [
            'tenant_id' => $actor->tenant_id, 'context_type' => 'tenant', 'actor_type' => 'user',
            'actor_user_id' => $actor->id, 'actor_label' => 'Synthetic actor',
            'event_type' => 'accommodation.assignment.confirmed', 'entity_type' => 'accommodation_assignment',
            'entity_id' => $ids[0], 'entity_label' => 'إقامة synthetic '.$i,
            'metadata' => json_encode(['synthetic' => str_repeat('x', 16384)]),
            'correlation_id' => 'phase3-synthetic', 'source' => 'web',
            'created_at' => CarbonImmutable::parse('2026-10-01 UTC')->addSeconds($i)->format('Y-m-d H:i:s'),
        ];
    }
    foreach (array_chunk($auditRows, 50) as $chunk) DB::table('audit_logs')->insert($chunk);

    $metrics = (object) ['active' => false, 'count' => 0, 'db_ms' => 0, 'queries' => []];
    DB::listen(function (QueryExecuted $query) use ($metrics): void {
        if (! $metrics->active) return;
        $metrics->count++;
        $metrics->db_ms += $query->time;
        if (! isset($metrics->queries[$query->sql])) {
            $metrics->queries[$query->sql] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'count' => 0];
        }
        $metrics->queries[$query->sql]['count']++;
    });
    $percentile = function (array $values, float $p): float {
        sort($values);
        return round((float) $values[max(0, (int) ceil(count($values) * $p) - 1)], 4);
    };
    $measure = function (string $name, Closure $operation, int $samples = 21) use ($metrics, $percentile, $driver): array {
        $rows = [];
        $plans = [];
        for ($i = -2; $i < $samples; $i++) {
            DB::beginTransaction();
            $metrics->count = 0; $metrics->db_ms = 0; $metrics->queries = [];
            $metrics->active = true;
            if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            try {
                $response = $operation();
                $elapsed = (hrtime(true) - $start) / 1e6;
                $peak = memory_get_peak_usage();
                $metrics->active = false;
                $payload = is_string($response) ? $response : json_encode($response);
                if ($i >= 0) $rows[] = ['ms' => $elapsed, 'queries' => $metrics->count, 'db_ms' => $metrics->db_ms,
                    'memory_bytes' => $memory, 'peak_bytes' => $peak, 'peak_delta_bytes' => max(0, $peak - $memory),
                    'payload_bytes' => strlen($payload)];
                if ($i === 0 && $driver === 'mysql') {
                    foreach ($metrics->queries as $query) {
                        if (! str_starts_with(strtolower($query['sql']), 'select')) continue;
                        $plans[] = ['query' => $query['sql'], 'executions' => $query['count'],
                            'plan' => DB::select('EXPLAIN '.$query['sql'], $query['bindings'])];
                    }
                }
            } finally {
                $metrics->active = false;
                DB::rollBack();
            }
        }
        $summary = ['scenario' => $name, 'samples' => $samples,
            'median_ms' => $percentile(array_column($rows, 'ms'), .5), 'p95_ms' => $percentile(array_column($rows, 'ms'), .95),
            'median_queries' => $percentile(array_column($rows, 'queries'), .5),
            'median_db_ms' => $percentile(array_column($rows, 'db_ms'), .5),
            'median_memory_bytes' => $percentile(array_column($rows, 'memory_bytes'), .5),
            'median_peak_bytes' => $percentile(array_column($rows, 'peak_bytes'), .5),
            'median_peak_delta_bytes' => $percentile(array_column($rows, 'peak_delta_bytes'), .5),
            'payload_bytes' => $rows[0]['payload_bytes'], 'raw' => $rows, 'explain' => $plans];
        fwrite(STDERR, $name.' median='.$summary['median_ms'].'ms queries='.$summary['median_queries'].PHP_EOL);
        return $summary;
    };
    $results = [];
    foreach ([1, 10, 50, 100] as $size) {
        $results[] = $measure('api_confirm_'.$size, fn () => $size === 1
            ? spaPostJson('/api/v1/accommodation/assignments/'.$ids[0].'/confirm', [], $world['header'])->assertOk()->getContent()
            : spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
                'season_family_group_id' => $family->id, 'assignment_ids' => array_slice($ids, 0, $size),
            ], $world['header'])->assertOk()->getContent());
    }
    $results[] = $measure('api_assignment_list_100', fn () => spaGetJson('/api/v1/accommodation/assignments?per_page=100', $world['header'])->assertOk()->getContent());
    $results[] = $measure('api_audit_search_1000', fn () => spaGetJson('/api/v1/audit-logs?search='.urlencode('إقامة').'&per_page=100', $world['header'])->assertOk()->getContent());
    $results[] = $measure('capacity_decrease_history_1000', fn () => withTenant($actor->tenant, function () use ($world): string {
        app(AccommodationCapacityGuard::class)->assertCapacityDecreaseAllowed(AccommodationSeasonUnit::query()->findOrFail($world['season_unit']['id']), 200);
        return 'ok';
    }), 7);
    $label = getenv('PHASE3_LABEL') ?: 'unnamed';
    if (! preg_match('/^[a-z0-9_-]+$/', $label)) throw new RuntimeException('Invalid benchmark label.');
    $directory = base_path('../docs/performance/phase3');
    if (! is_dir($directory)) mkdir($directory, 0777, true);
    file_put_contents($directory.'/'.$driver.'-'.$label.'.json', json_encode([
        'label' => $label, 'driver' => $driver, 'version' => $driver === 'mysql' ? DB::selectOne('select version() as version')->version : DB::selectOne('select sqlite_version() as version')->version,
        'php' => PHP_VERSION, 'fixture' => ['drafts' => 100, 'historical_stays' => 1000, 'audit_rows' => 1000, 'audit_metadata_bytes_per_row' => 16384],
        'scope' => 'In-process Laravel HTTP kernel; no network/TLS. Two warmups, rollback between samples. Synthetic data only.',
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
});
