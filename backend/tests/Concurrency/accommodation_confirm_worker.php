<?php

/**
 * Gate E confirmation / admin race worker.
 *
 * Actions:
 * - confirm (default): confirm one draft assignment
 * - decrease_capacity: patch season-unit capacity under locks
 * - deactivate_position: deactivate a season position under locks
 *
 * Usage: php tests/Concurrency/accommodation_confirm_worker.php <base64 json>
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Accommodation\Actions\AccommodationConfirmationActions;
use App\Modules\Accommodation\Actions\AccommodationOperationsActions;
use App\Modules\Accommodation\Actions\AccommodationSeasonPositionActions;
use App\Modules\Accommodation\Actions\AccommodationSeasonUnitActions;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);

DB::statement('SET SESSION innodb_lock_wait_timeout = 20');

$db = (string) DB::selectOne('select database() as db')->db;
if ($db !== 'erp_hajj_testing') {
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => 'WRONG_DATABASE', 'db' => $db], JSON_THROW_ON_ERROR));
    exit(1);
}

Event::listen(TransactionCommitting::class, function () use ($job): void {
    $holdMs = (int) ($job['hold_ms'] ?? 0);
    if ($holdMs > 0) {
        usleep($holdMs * 1000);
    }
});

$startAt = (float) ($job['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(500);
}
$released = microtime(true);

$outcome = app(TenantContext::class)->runAsTenant(
    Tenant::query()->findOrFail((int) $job['tenant_id']),
    function () use ($job): array {
        $season = Season::query()->findOrFail((int) $job['season_id']);
        $actor = User::query()->findOrFail((int) $job['actor_id']);
        $request = Request::create('/concurrency-confirm', 'POST');
        $action = (string) ($job['action'] ?? 'confirm');

        return app(SeasonContext::class)->runInSeason($season, function () use ($actor, $job, $request, $action): array {
            try {
                match ($action) {
                    'confirm' => app(AccommodationConfirmationActions::class)->confirmOne(
                        $actor,
                        AccommodationAssignment::query()->findOrFail((int) $job['assignment_id']),
                        $request,
                    ),
                    'confirm_batch' => app(AccommodationConfirmationActions::class)->confirmMany(
                        $actor, array_map('intval', $job['assignment_ids']), $request,
                    ),
                    'decrease_capacity' => app(AccommodationSeasonUnitActions::class)->update(
                        $actor,
                        AccommodationSeasonUnit::query()->findOrFail((int) $job['season_unit_id']),
                        ['capacity' => (int) $job['new_capacity']],
                        $request,
                    ),
                    'deactivate_position' => app(AccommodationSeasonPositionActions::class)->update(
                        $actor,
                        AccommodationSeasonPosition::query()->findOrFail((int) $job['season_position_id']),
                        ['is_active' => false],
                        $request,
                    ),
                    'check_out' => app(AccommodationOperationsActions::class)->checkOut(
                        $actor,
                        AccommodationAssignment::query()->findOrFail((int) $job['assignment_id']),
                        $request,
                        isset($job['occurred_at']) ? (string) $job['occurred_at'] : null,
                    ),
                    'cancel' => app(AccommodationOperationsActions::class)->cancel(
                        $actor,
                        AccommodationAssignment::query()->findOrFail((int) $job['assignment_id']),
                        $request,
                    ),
                    'relocate' => app(AccommodationOperationsActions::class)->relocate(
                        $actor,
                        AccommodationAssignment::query()->findOrFail((int) $job['assignment_id']),
                        (int) $job['destination_season_unit_id'],
                        isset($job['destination_season_position_id']) ? (int) $job['destination_season_position_id'] : null,
                        $request,
                        isset($job['occurred_at']) ? (string) $job['occurred_at'] : null,
                        (bool) ($job['check_in_after'] ?? false),
                    ),
                    default => throw new RuntimeException('Unknown action '.$action),
                };

                return ['ok' => true, 'code' => 'OK', 'action' => $action];
            } catch (AccommodationDomainException $e) {
                return ['ok' => false, 'code' => $e->errorCode(), 'action' => $action];
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'code' => 'UNEXPECTED',
                    'action' => $action,
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ];
            }
        });
    },
);

fwrite(STDOUT, json_encode([
    ...$outcome,
    'late_ms' => (int) round(($released - $startAt) * 1000),
    'elapsed_ms' => (int) round((microtime(true) - $released) * 1000),
], JSON_THROW_ON_ERROR));
