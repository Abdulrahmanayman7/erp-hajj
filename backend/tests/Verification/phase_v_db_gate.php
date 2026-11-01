<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Fail-closed DB identity gate for Phase V multi-process Demand Group verification.
 *
 * @return array{app_env: string, connection: string, database: string, host: string, version: string, driver: string}
 */
function phaseVAssertTestingDb(string $label): array
{
    $appEnv = (string) app()->environment();
    $default = (string) config('database.default');
    $conn = config("database.connections.{$default}");
    $configDb = (string) ($conn['database'] ?? '');
    $host = (string) ($conn['host'] ?? '');
    $driver = (string) ($conn['driver'] ?? $default);

    $row = DB::selectOne('select database() as db, @@version as version');
    $db = (string) ($row->db ?? '');
    $version = (string) ($row->version ?? '');

    if ($appEnv !== 'testing') {
        fwrite(STDERR, "[{$label}] REFUSE: APP_ENV={$appEnv} (expected testing)\n");
        exit(2);
    }

    if (! in_array($driver, ['mysql', 'mariadb'], true) && $default !== 'mysql') {
        fwrite(STDERR, "[{$label}] REFUSE: connection={$default} driver={$driver}\n");
        exit(2);
    }

    if ($configDb !== 'erp_hajj_testing' || $db !== 'erp_hajj_testing') {
        fwrite(STDERR, "[{$label}] REFUSE: config_db={$configDb} select_db={$db}\n");
        exit(2);
    }

    if ($host !== '127.0.0.1' && $host !== 'localhost') {
        fwrite(STDERR, "[{$label}] REFUSE: host={$host} (expected 127.0.0.1)\n");
        exit(2);
    }

    if ($db === 'erp_hajj' || $configDb === 'erp_hajj') {
        fwrite(STDERR, "[{$label}] REFUSE: erp_hajj detected\n");
        exit(3);
    }

    return [
        'app_env' => $appEnv,
        'connection' => $default,
        'database' => $db,
        'host' => $host,
        'version' => $version,
        'driver' => $driver !== '' ? $driver : $default,
    ];
}

/**
 * @return array<string, string>
 */
function phaseVChildEnv(): array
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
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array',
    ];
}
