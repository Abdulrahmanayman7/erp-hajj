<?php

/**
 * Gate B lock-anchor race worker. One insert attempt per process/connection.
 *
 * Usage: php tests/Concurrency/accommodation_lock_worker.php <base64 json>
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);

$startAt = (float) ($job['start_at'] ?? 0);
$holdMs = (int) ($job['hold_ms'] ?? 0);
$tenantId = (int) $job['tenant_id'];
$seasonId = (int) $job['season_id'];
$seasonPilgrimId = (int) $job['season_pilgrim_id'];

while (microtime(true) < $startAt) {
    usleep(1000);
}
$lateMs = (int) ((microtime(true) - $startAt) * 1000);

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        (string) env('DB_HOST', '127.0.0.1'),
        (string) env('DB_PORT', '3306'),
        (string) env('DB_DATABASE'),
    ),
    (string) env('DB_USERNAME', 'root'),
    (string) env('DB_PASSWORD', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$db = (string) $pdo->query('select database()')->fetchColumn();
if ($db !== 'erp_hajj_testing') {
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => 'WRONG_DATABASE', 'db' => $db], JSON_THROW_ON_ERROR));
    exit(1);
}

$started = microtime(true);
$ok = false;
$code = 'OK';

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'insert into accommodation_pilgrim_locks (tenant_id, season_id, season_pilgrim_id, created_at, updated_at) values (?, ?, ?, utc_timestamp(), utc_timestamp())',
    );
    $stmt->execute([$tenantId, $seasonId, $seasonPilgrimId]);
    if ($holdMs > 0) {
        usleep($holdMs * 1000);
    }
    $pdo->commit();
    $ok = true;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = ((string) $e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate'))
        ? 'DUPLICATE'
        : 'UNEXPECTED';
}

fwrite(STDOUT, json_encode([
    'ok' => $ok,
    'code' => $code,
    'late_ms' => $lateMs,
    'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
], JSON_THROW_ON_ERROR));
