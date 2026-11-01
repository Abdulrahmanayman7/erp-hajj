<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Fail-closed DB identity gate for Phase IV QV/CV processes.
 *
 * @return array{database: string, host: string, version: string, queue: string}
 */
function phaseIvAssertTestingDb(string $label): array
{
    $default = (string) config('database.default');
    $conn = config("database.connections.{$default}");
    $configDb = (string) ($conn['database'] ?? '');
    $host = (string) ($conn['host'] ?? '');
    $row = DB::selectOne('select database() as db, @@version as version');
    $db = (string) ($row->db ?? '');
    $version = (string) ($row->version ?? '');

    if ($configDb !== 'erp_hajj_testing' || $db !== 'erp_hajj_testing') {
        fwrite(STDERR, "[{$label}] REFUSE: config_db={$configDb} select_db={$db}\n");
        exit(2);
    }
    if ($db === 'erp_hajj' || $configDb === 'erp_hajj') {
        fwrite(STDERR, "[{$label}] REFUSE: erp_hajj detected\n");
        exit(3);
    }

    $queue = (string) config('queue.default');

    return [
        'database' => $db,
        'host' => $host,
        'version' => $version,
        'queue' => $queue,
    ];
}

/**
 * @return array<string, string>
 */
function phaseIvChildEnv(string $queueConnection = 'database'): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($connection['port'] ?? '3306'),
        'DB_DATABASE' => 'erp_hajj_testing',
        'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => $queueConnection,
        'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array',
    ];
}
