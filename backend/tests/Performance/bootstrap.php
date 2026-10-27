<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Runs before Laravel and before RefreshDatabase / DatabaseTruncation can execute.
$env = fn (string $key): string => (string) ($_ENV[$key] ?? getenv($key) ?: '');
if ($env('DB_CONNECTION') === 'sqlite' && $env('DB_DATABASE') === ':memory:' && $env('DB_URL') === '') {
    return;
}
if ($env('APP_ENV') !== 'testing' || $env('DB_CONNECTION') !== 'mysql'
    || $env('DB_DATABASE') !== 'erp_hajj_testing' || $env('DB_HOST') !== '127.0.0.1'
    || $env('DB_PORT') !== '33117' || $env('DB_URL') !== '') {
    throw new RuntimeException('Phase 3 refuses any database except memory SQLite or the isolated port 33117 instance.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33117;dbname=erp_hajj_testing', $env('DB_USERNAME'), $env('DB_PASSWORD'));
$server = $pdo->query('select database() as db, @@port as port, @@datadir as datadir')->fetch(PDO::FETCH_ASSOC);
if ($server['db'] !== 'erp_hajj_testing' || (int) $server['port'] !== 33117
    || ! str_contains(str_replace('\\', '/', strtolower($server['datadir'])), '/storage/framework/testing/phase3-mysql-')) {
    throw new RuntimeException('The server identity is not a disposable Phase 3 instance.');
}
